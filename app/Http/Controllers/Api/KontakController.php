<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KontakSekolah;

class KontakController extends Controller
{
    public function show()
    {
        $k = KontakSekolah::query()->first() ?? new KontakSekolah();

        return response()->json($k->tampil());
    }
}