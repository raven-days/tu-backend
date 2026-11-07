<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class KonfirmasiSandiController extends Controller
{
    public const MENIT = 5; // lama izin berlaku setelah sandi dikonfirmasi

    /** Kunci cache ini terikat pada token yang sedang dipakai, jadi tidak berlaku di perangkat lain. */
    public static function kunci(Request $request): string
    {
        return 'sandi_ulang:' . $request->user()->currentAccessToken()->id;
    }

    public function store(Request $request)
    {
        $data = $request->validate(['sandi' => 'required|string']);

        if (!Hash::check($data['sandi'], $request->user()->password)) {
            AuditLog::catat('konfirmasi_sandi_gagal', 'Konfirmasi kata sandi gagal.');

            return response()->json(['message' => 'Kata sandi salah.'], 422);
        }

        Cache::put(self::kunci($request), true, now()->addMinutes(self::MENIT));

        AuditLog::catat('konfirmasi_sandi', 'Kata sandi dikonfirmasi untuk aksi sensitif.');

        return response()->json(['berlaku_menit' => self::MENIT]);
    }
}