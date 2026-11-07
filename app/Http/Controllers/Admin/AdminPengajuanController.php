<?php

namespace App\Http\Controllers\Admin;

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

        $bawaan = [
            'Diverifikasi' => 'Berkas sudah diperiksa.',
            'Selesai' => 'Hasil sudah dapat diambil di Tata Usaha.',
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
}