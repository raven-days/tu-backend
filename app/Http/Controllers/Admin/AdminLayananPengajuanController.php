<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LayananPengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminLayananPengajuanController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => LayananPengajuan::orderBy('urutan')->orderBy('id')->get()->map(fn ($l) => $l->tampil()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);

        $dasar = Str::slug($data['nama']) ?: 'layanan';
        $slug = $dasar;
        for ($i = 2; LayananPengajuan::where('slug', $slug)->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        $l = LayananPengajuan::create($data + ['slug' => $slug]);
        AuditLog::catat('layanan_tambah', "Tambah layanan pengajuan: {$l->nama} ({$l->akses}).", null, 'layanan', $l->id);

        return response()->json($l->tampil(), 201);
    }

    public function update(Request $request, LayananPengajuan $layanan)
    {
        $data = $this->validasi($request);
        $lama = $layanan->akses;
        $layanan->update($data);

        $ket = $lama !== $layanan->akses ? " Akses {$lama} menjadi {$layanan->akses}." : '';
        AuditLog::catat('layanan_ubah', "Ubah layanan pengajuan: {$layanan->nama}.{$ket}", null, 'layanan', $layanan->id);

        return response()->json($layanan->tampil());
    }

    public function destroy(LayananPengajuan $layanan)
    {
        AuditLog::catat('layanan_hapus', "Hapus layanan pengajuan: {$layanan->nama}.", null, 'layanan', $layanan->id);
        $layanan->delete();

        return response()->json(['message' => 'Layanan dihapus.']);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:150',
            'deskripsi' => 'nullable|string|max:500',
            'akses' => 'required|in:guest,login,manual',
            'syarat' => 'nullable|string|max:2000',
            'instruksi' => 'required_if:akses,manual|nullable|string|max:2000',
            'wajib_lampiran' => 'required|boolean',
            'aktif' => 'required|boolean',
            'urutan' => 'nullable|integer|min:0|max:999',
        ], [
            'instruksi.required_if' => 'Isi instruksi untuk pengunjung (apa yang harus mereka lakukan).',
        ]) + ['urutan' => (int) $request->input('urutan', 0)];
    }
}