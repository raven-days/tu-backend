<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuratKeputusan;
use Illuminate\Support\Facades\Storage;

class SuratKeputusanController extends Controller
{
    public function index()
    {
        return SuratKeputusan::orderByDesc('tahun')->orderByDesc('id')->get()->map(fn ($s) => [
            'id' => $s->id,
            'nomor' => $s->nomor,
            'tentang' => $s->tentang,
            'tahun' => $s->tahun,
            'kategori' => $s->kategori,
            'file' => $s->file_path ? Storage::disk('public')->url($s->file_path) : null,
        ]);
    }
}