<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\SuratKeputusan;
use App\Models\Pengumuman;

class RingkasanController extends Controller
{
    public function index()
    {
        return response()->json([
            'sk' => SuratKeputusan::count(),
            'pengumuman' => Pengumuman::count(),
            'guru' => Pegawai::where('kategori', 'guru')->count(),
            'tendik' => Pegawai::where('kategori', 'tendik')->count(),
        ]);
    }
}