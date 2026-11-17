<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DokumenAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnggaranController extends Controller
{
    /** Pengunjung biasa hanya melihat dokumen publik. Akun yang login melihat semuanya. */
    public function index(Request $request)
    {
        $masuk = $this->masuk($request);
        $kategori = $request->query('kategori') === 'laporan' ? 'laporan' : 'regulasi';

        $q = DokumenAnggaran::where('kategori', $kategori);
        $tersembunyi = 0;

        if (!$masuk) {
            $tersembunyi = (clone $q)->where('akses', 'login')->count();
            $q->where('akses', 'publik');
        }

        $data = $q->orderByDesc('tahun')->orderBy('periode')->orderByDesc('id')->get()->map(fn ($d) => [
            'id' => $d->id,
            'kategori' => $d->kategori,
            'judul' => $d->judul,
            'nomor' => $d->nomor,
            'sumber_dana' => $d->sumber_dana,
            'penerbit' => $d->penerbit,
            'tahun' => $d->tahun,
            'periode' => $d->periode,
            'akses' => $d->akses,
            'file' => $d->file_path ? "/anggaran/{$d->id}/berkas" : null,
        ]);

        return response()->json(['data' => $data, 'tersembunyi' => $tersembunyi]);
    }

    /** Buka PDF. Dokumen khusus login ditolak untuk pengunjung biasa. */
    public function berkas(Request $request, DokumenAnggaran $dok)
    {
        if ($dok->akses === 'login' && !$this->masuk($request)) {
            abort(403, 'Dokumen ini khusus akun yang sudah masuk.');
        }

        abort_unless($dok->file_path && Storage::disk('public')->exists($dok->file_path), 404, 'File tidak ditemukan.');

        return Storage::disk('public')->response($dok->file_path, (Str::slug($dok->judul) ?: 'dokumen') . '.pdf');
    }

    private function masuk(Request $request): bool
    {
        $u = $request->user('sanctum');

        return $u && $u->is_active;
    }
}