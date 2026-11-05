<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        return Pengumuman::orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map->tampil();
    }

    public function show(Pengumuman $pengumuman)
    {
        return response()->json($pengumuman->tampil());
    }
}