<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;

class PegawaiController extends Controller
{
    public function index()
    {
        $ambil = fn (string $k) => Pegawai::where('kategori', $k)
            ->orderBy('urutan')
            ->get(['nama', 'jabatan']);

        return response()->json([
            'guru' => $ambil('guru'),
            'tendik' => $ambil('tendik'),
        ]);
    }
}