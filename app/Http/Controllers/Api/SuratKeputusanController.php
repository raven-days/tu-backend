<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuratKeputusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SuratKeputusanController extends Controller
{
    /** Daftar SK atau SOP (?jenis=sk | sop). Khusus akun yang login. */
    public function index(Request $request)
    {
        $jenis = $request->query('jenis') === 'sop' ? 'sop' : 'sk';

        return SuratKeputusan::where('jenis', $jenis)
            ->orderByDesc('tahun')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'jenis' => $s->jenis,
                'nomor' => $s->nomor,
                'tentang' => $s->tentang,
                'penerbit' => $s->penerbit,
                'bidang' => $s->bidang,
                'tahun' => $s->tahun,
                'kategori' => $s->kategori,
                'file' => $s->file_path ? "/sk/{$s->id}/berkas" : null,
            ]);
    }

    /** Buka PDF (harus login). */
    public function berkas(SuratKeputusan $sk)
    {
        abort_unless($sk->file_path && Storage::disk('public')->exists($sk->file_path), 404, 'File tidak ditemukan.');

        return Storage::disk('public')->response($sk->file_path, (Str::slug($sk->nomor) ?: 'dokumen') . '.pdf');
    }
}