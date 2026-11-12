<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsetDokumen;
use App\Models\AuditLog;
use App\Support\ProfilSekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AsetController extends Controller
{
    /** Publik: info sekolah. */
    public function profil()
    {
        return response()->json(ProfilSekolah::ambil());
    }

    /** Wajib login: hanya dokumen yang boleh dilihat peran akun ini. */
    public function dokumen(Request $request)
    {
        $user = $request->user();

        return response()->json(
            AsetDokumen::urut()->get()
                ->filter(fn ($d) => $d->bisaDilihat($user))
                ->map(fn ($d) => $d->tampil())
                ->values()
        );
    }

    /** Wajib login + peran yang diizinkan. ?unduh=1 untuk mengunduh. */
    public function file(Request $request, AsetDokumen $dokumen)
    {
        abort_unless($dokumen->bisaDilihat($request->user()), 403, 'Anda tidak memiliki akses ke dokumen ini.');
        abort_unless(Storage::disk('local')->exists($dokumen->file_path), 404, 'File tidak ditemukan di server.');

        AuditLog::catat('aset_dokumen_buka', "Buka dokumen aset: {$dokumen->judul}", null, 'aset_dokumen', $dokumen->id);

        return Storage::disk('local')->response(
            $dokumen->file_path,
            $dokumen->file_nama,
            [
                'Content-Type' => $dokumen->file_mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $request->boolean('unduh') ? 'attachment' : 'inline',
        );
    }
}