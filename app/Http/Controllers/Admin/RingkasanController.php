<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\SuratKeputusan;

class RingkasanController extends Controller
{
    public function index()
    {
        return response()->json([
            'sk' => SuratKeputusan::count(),
            'guru' => Pegawai::where('kategori', 'guru')->count(),
            'tendik' => Pegawai::where('kategori', 'tendik')->count(),
        ]);
    }
}