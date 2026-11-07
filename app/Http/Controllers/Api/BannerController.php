<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;

class BannerController extends Controller
{
    public function index()
    {
        return Banner::where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map->tampil()
            ->values();
    }
}