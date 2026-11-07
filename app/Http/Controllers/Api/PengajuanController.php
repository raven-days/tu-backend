<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use App\Models\PengajuanLampiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PengajuanController extends Controller
{
    private const MAKS_LAMPIRAN = 5;

    /** Daftar pengajuan milik pengguna yang login. */
    public function index(Request $request)
    {
        return Pengajuan::with('user')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map->tampil(false)
            ->values();
    }

    /** Kirim pengajuan baru. */
    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(true));
        $user = $request->user();

        $pengajuan = DB::transaction(function () use ($data, $request, $user) {
            $p = Pengajuan::create([
                'user_id' => $user->id,
                'jenis' => $data['jenis'],
                'perihal' => trim($data['perihal']),
                'tanggal_dibutuhkan' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $p->update(['nomor' => sprintf('TU-%s-%05d', now()->format('Y'), $p->id)]);
            $this->simpanLampiran($p, $request->file('lampiran', []));
            $p->catat('Diajukan', 'Menunggu', $user->name);

            return $p;
        });

        return response()->json($pengajuan->fresh(['user', 'riwayat', 'lampiran'])->tampil(), 201);
    }

    /** Detail pengajuan (pemiliknya atau petugas TU). */
    public function show(Request $request, Pengajuan $pengajuan)
    {
        $this->izinkan($request, $pengajuan);

        return response()->json($pengajuan->load(['user', 'riwayat', 'lampiran'])->tampil());
    }

    /** Pemohon memperbaiki isian lalu mengirim ulang. */
    public function kirimUlang(Request $request, Pengajuan $pengajuan)
    {
        abort_unless($pengajuan->user_id === $request->user()->id, 403, 'Anda tidak memiliki akses.');

        if ($pengajuan->status !== 'Perlu Perbaikan') {
            return response()->json(['message' => 'Pengajuan ini tidak sedang menunggu perbaikan.'], 422);
        }

        $data = $request->validate($this->aturan(false));

        $tetap = array_map('intval', $data['lampiran_tetap'] ?? []);
        $baru = $request->file('lampiran', []);
        $adaTetap = $pengajuan->lampiran->filter(fn ($l) => in_array($l->id, $tetap, true));

        if ($adaTetap->count() + count($baru) > self::MAKS_LAMPIRAN) {
            return response()->json(['message' => 'Maksimal ' . self::MAKS_LAMPIRAN . ' lampiran.'], 422);
        }

        $hapusPath = [];

        DB::transaction(function () use ($pengajuan, $data, $request, $tetap, $baru, &$hapusPath) {
            $pengajuan->update([
                'jenis' => $data['jenis'],
                'perihal' => trim($data['perihal']),
                'tanggal_dibutuhkan' => $data['tanggal'],
                'keterangan' => $data['keterangan'] ?? null,
                'status' => 'Menunggu',
                'catatan_field' => null,
                'revisi' => $pengajuan->revisi + 1,
            ]);

            foreach ($pengajuan->lampiran as $l) {
                if (!in_array($l->id, $tetap, true)) {
                    $hapusPath[] = $l->path;
                    $l->delete();
                }
            }

            $this->simpanLampiran($pengajuan, $baru);
            $pengajuan->catat('Dikirim ulang', 'Menunggu', $request->user()->name);
        });

        foreach ($hapusPath as $path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json($pengajuan->fresh(['user', 'riwayat', 'lampiran'])->tampil());
    }

    /** Buka file lampiran (hanya pemilik pengajuan atau petugas TU). */
    public function lampiran(Request $request, PengajuanLampiran $lampiran)
    {
        $this->izinkan($request, $lampiran->pengajuan);

        abort_unless(Storage::disk('public')->exists($lampiran->path), 404, 'File tidak ditemukan.');

        return Storage::disk('public')->response($lampiran->path, $lampiran->nama_asli);
    }

    private function aturan(bool $baru): array
    {
        $aturan = [
            'jenis' => 'required|string|max:100',
            'perihal' => 'required|string|min:5|max:255',
            'tanggal' => 'required|date',
            'keterangan' => 'nullable|string|max:2000',
            'lampiran' => 'nullable|array|max:' . self::MAKS_LAMPIRAN,
            'lampiran.*' => 'file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];

        if (!$baru) {
            $aturan['lampiran_tetap'] = 'nullable|array';
            $aturan['lampiran_tetap.*'] = 'integer';
        }

        return $aturan;
    }

    private function izinkan(Request $request, Pengajuan $pengajuan): void
    {
        $u = $request->user();

        abort_unless($u->punyaIzin('pengajuan') || $pengajuan->user_id === $u->id, 403, 'Anda tidak memiliki akses.');
    }

    private function simpanLampiran(Pengajuan $p, array $files): void
    {
        foreach ($files as $f) {
            $p->lampiran()->create([
                'nama_asli' => $f->getClientOriginalName(),
                'path' => $f->store("pengajuan/{$p->id}", 'public'),
                'ukuran' => $f->getSize(),
            ]);
        }
    }
}