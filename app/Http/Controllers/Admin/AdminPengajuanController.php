<?php

namespace App\Http\Controllers\Admin;

use App\Models\PengajuanLampiran;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;

class AdminPengajuanController extends Controller
{
    private const KOLOM = ['jenis', 'perihal', 'tanggal', 'keterangan', 'lampiran'];

    /** Antrian: semua pengajuan, terbaru di atas. */
    public function index(Request $request)
    {
        $q = Pengajuan::with('user')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return $q->get()->map->tampil(false)->values();
    }

    /** Ubah status: verifikasi, minta perbaikan, tolak, setujui, selesai. */
    public function status(Request $request, Pengajuan $pengajuan)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'catatan' => 'nullable|string|max:1000',
            'catatan_field' => 'nullable|array',
            'catatan_field.*' => 'nullable|string|max:500',
        ]);

        $tujuan = $data['status'];
        $boleh = Pengajuan::TRANSISI[$pengajuan->status] ?? [];

        if (!in_array($tujuan, $boleh, true)) {
            return response()->json([
                'message' => "Status tidak dapat diubah dari {$pengajuan->status} ke {$tujuan}.",
            ], 422);
        }

        $catatan = trim($data['catatan'] ?? '');

        // catatan per kolom: hanya kolom yang dikenal, buang yang kosong
        $field = array_filter(
            array_map(fn ($v) => trim((string) $v), array_intersect_key($data['catatan_field'] ?? [], array_flip(self::KOLOM))),
            fn ($v) => $v !== ''
        );

        if ($tujuan === 'Perlu Perbaikan') {
            if ($catatan === '' && !$field) {
                return response()->json(['message' => 'Isi catatan umum atau minimal satu catatan pada kolom.'], 422);
            }
            if ($catatan === '') {
                $catatan = 'Mohon perbaiki isian sesuai catatan pada kolom yang ditandai, lalu kirim ulang.';
            }
        } else {
            $field = [];
        }

        if ($tujuan === 'Ditolak' && mb_strlen($catatan) < 5) {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        if ($tujuan === 'Selesai' && !$pengajuan->hasil()->exists() && !$pengajuan->ringkasPengambilan()) {
            return response()->json([
                'message' => 'Tambahkan dokumen hasil atau petunjuk pengambilan sebelum menandai selesai.',
            ], 422);
        }

        $bawaan = [
            'Diverifikasi' => 'Berkas sudah diperiksa.',
            'Selesai' => $pengajuan->ringkasPengambilan() ?? 'Hasil dapat diunduh pada halaman pengajuan ini.',
        ];
        if ($catatan === '' && isset($bawaan[$tujuan])) {
            $catatan = $bawaan[$tujuan];
            }

        DB::transaction(function () use ($pengajuan, $tujuan, $catatan, $field, $request) {
            $pengajuan->update([
                'status' => $tujuan,
                'catatan_field' => $field ?: null,
            ]);
            $pengajuan->catat($tujuan, $tujuan, $request->user()->name, $catatan ?: null, $field ?: null);
        });
        AuditLog::catat('pengajuan_status', "Ubah status {$pengajuan->nomor} menjadi {$tujuan}.", null, 'pengajuan', $pengajuan->id);

        return response()->json($pengajuan->fresh(['user', 'riwayat', 'lampiran'])->tampil());
    }
    private const MAKS_HASIL = 5;

    /** Tambah dokumen hasil dan/atau petunjuk pengambilan (saat Disetujui atau Selesai). */
    public function hasil(Request $request, Pengajuan $pengajuan)
    {
        if (!in_array($pengajuan->status, ['Disetujui', 'Selesai'], true)) {
            return response()->json(['message' => 'Hasil hanya bisa diisi setelah pengajuan disetujui.'], 422);
        }

        $v = $request->validate([
            'hasil' => ['nullable', 'array', 'max:' . self::MAKS_HASIL],
            'hasil.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
            'pengambilan' => ['nullable', 'array'],
            'pengambilan.mode' => ['nullable', 'in:ambil,unduh'],
            'pengambilan.tanggal' => ['nullable', 'date', 'after_or_equal:today'],
            'pengambilan.jam_mulai' => ['nullable', 'date_format:H:i'],
            'pengambilan.jam_selesai' => ['nullable', 'date_format:H:i'],
            'pengambilan.lokasi' => ['nullable', 'string', 'max:120'],
            'pengambilan.catatan' => ['nullable', 'string', 'max:300'],
            'hapus_pengambilan' => ['nullable', 'boolean'],
        ], [
            'hasil.*.mimes' => 'Dokumen hasil harus PDF, gambar (JPG/PNG), Word, atau Excel.',
            'hasil.*.max' => 'Ukuran tiap dokumen hasil maksimal 5 MB.',
            'pengambilan.tanggal.after_or_equal' => 'Tanggal pengambilan tidak boleh sebelum hari ini.',
            'pengambilan.jam_mulai.date_format' => 'Format jam mulai tidak valid.',
            'pengambilan.jam_selesai.date_format' => 'Format jam selesai tidak valid.',
        ]);

        $files = $request->file('hasil', []);
        $p = $v['pengambilan'] ?? [];
        $mode = $p['mode'] ?? null;

        if ($pengajuan->hasil()->count() + count($files) > self::MAKS_HASIL) {
            return response()->json(['message' => 'Maksimal ' . self::MAKS_HASIL . ' dokumen hasil.'], 422);
        }

        if (!empty($p['jam_selesai']) && empty($p['jam_mulai'])) {
            return response()->json(['message' => 'Isi jam mulai bila jam selesai diisi.'], 422);
        }
        if (!empty($p['jam_selesai']) && !empty($p['jam_mulai']) && $p['jam_selesai'] <= $p['jam_mulai']) {
            return response()->json(['message' => 'Jam selesai harus setelah jam mulai.'], 422);
        }

        if (!$files && !$mode && empty($v['hapus_pengambilan'])) {
            return response()->json(['message' => 'Tidak ada yang disimpan. Pilih dokumen atau isi petunjuk pengambilan.'], 422);
        }

        $catatan = [];

        DB::transaction(function () use ($pengajuan, $files, $p, $mode, $v, $request, &$catatan) {
            foreach ($files as $f) {
                $pengajuan->hasil()->create([
                    'jenis' => 'hasil',
                    'nama_asli' => $f->getClientOriginalName(),
                    'path' => $f->store("pengajuan/{$pengajuan->id}/hasil", 'public'),
                    'ukuran' => $f->getSize(),
                ]);
            }
            if ($files) {
                $catatan[] = count($files) . ' dokumen hasil ditambahkan.';
            }

            if (!empty($v['hapus_pengambilan'])) {
                $pengajuan->update(['pengambilan' => null]);
                $catatan[] = 'Petunjuk pengambilan dihapus.';
            } elseif ($mode) {
                $isi = ['mode' => $mode, 'catatan' => trim($p['catatan'] ?? '')];
                if ($mode === 'ambil') {
                    $isi += [
                        'tanggal' => $p['tanggal'] ?? null,
                        'jam_mulai' => $p['jam_mulai'] ?? null,
                        'jam_selesai' => $p['jam_selesai'] ?? null,
                        'lokasi' => trim($p['lokasi'] ?? '') ?: 'Kantor Tata Usaha',
                    ];
                }
                $pengajuan->update(['pengambilan' => array_filter($isi, fn ($x) => $x !== null && $x !== '')]);
                $catatan[] = $pengajuan->fresh()->ringkasPengambilan();
            }

            $pengajuan->catat('Hasil diperbarui', $pengajuan->status, $request->user()->name, implode(' ', array_filter($catatan)) ?: null);
        });

        return response()->json($pengajuan->fresh(['user', 'riwayat', 'lampiran'])->tampil());
    }

    public function hapusHasil(Request $request, Pengajuan $pengajuan, PengajuanLampiran $lampiran)
    {
        abort_unless($lampiran->pengajuan_id === $pengajuan->id && $lampiran->jenis === 'hasil', 404, 'Dokumen tidak ditemukan.');

        Storage::disk('public')->delete($lampiran->path);
        $lampiran->delete();
        $pengajuan->catat('Dokumen hasil dihapus', $pengajuan->status, $request->user()->name, $lampiran->nama_asli);

        return response()->json($pengajuan->fresh(['user', 'riwayat', 'lampiran'])->tampil());
    }
    }