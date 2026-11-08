<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermintaanReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Permintaan reset kata sandi dari halaman publik /lupa-kata-sandi.
 * Tidak ada email: permintaan masuk daftar untuk admin, yang lalu memberi kata sandi sementara.
 * Respons SELALU sama, apa pun username-nya, agar orang luar tidak bisa menebak akun yang ada.
 */
class LupaSandiController extends Controller
{
    public function store(Request $request)
    {
        $v = $request->validate([
            'identitas' => ['required', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:300'],
            'website' => ['nullable', 'string', 'max:200'], // jebakan bot: manusia tidak mengisinya
        ], [
            'identitas.required' => 'Isi NIS, NIP, atau username Anda.',
        ]);

        $jawaban = response()->json([
            'message' => 'Permintaan diterima. Tata Usaha akan memprosesnya pada jam layanan.',
        ], 202);

        // Bot: pura-pura berhasil, tidak disimpan.
        if (!empty($v['website'])) {
            return $jawaban;
        }

        $kunci = 'lupa-sandi:' . $request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json(['message' => 'Terlalu banyak permintaan.', 'retry_after' => $detik], 429)
                ->header('Retry-After', $detik);
        }
        RateLimiter::hit($kunci, 3600);

        $username = trim($v['identitas']);

        // Permintaan yang sama dan masih menunggu tidak dicatat dua kali.
        if (!PermintaanReset::where('status', 'baru')->where('username', $username)->exists()) {
            PermintaanReset::create([
                'user_id' => User::where('username', $username)->value('id'),
                'username' => $username,
                'catatan' => isset($v['catatan']) ? trim($v['catatan']) : null,
                'ip' => $request->ip(),
            ]);
        }

        return $jawaban;
    }
}