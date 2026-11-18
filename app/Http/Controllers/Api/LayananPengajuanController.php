<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LayananPengajuan;

class LayananPengajuanController extends Controller
{
    public function index()
    {
        $data = LayananPengajuan::where('aktif', true)->orderBy('urutan')->orderBy('id')->get()
            ->map(fn ($l) => $l->tampil());

        return response()->json(['data' => $data, 'batas' => $this->batas()]);
    }

    public function show(string $slug)
    {
        $l = LayananPengajuan::where('aktif', true)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $l->tampil(), 'batas' => $this->batas()]);
    }

    private function batas(): array
    {
        $c = config('pengajuan.tamu');

        return [
            'maks_file' => $c['maks_file'],
            'maks_mb' => round($c['maks_kb'] / 1024),
            'maks_total_mb' => round($c['maks_total_kb'] / 1024),
        ];
    }
}