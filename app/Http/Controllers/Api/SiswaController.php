<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    public function cari(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $like = str_replace(['%', '_'], ['\%', '\_'], $q);

        return Siswa::where(fn ($w) => $w->where('nis', 'like', "{$like}%")->orWhere('nama', 'like', "%{$like}%"))
            ->orderBy('nama')
            ->limit(8)
            ->get(['nis', 'nama', 'kelas']);
    }
}