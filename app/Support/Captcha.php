<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Captcha
{
    /** Cek token Cloudflare Turnstile dari kolom `captcha`. Produksi tanpa kunci = ditolak. */
    public static function lolos(Request $request): bool
    {
        $secret = config('services.turnstile.secret');
        if (!$secret) {
            return app()->environment('local');
        }

        $token = (string) $request->input('captcha');
        if ($token === '') {
            return false;
        }

        try {
            $res = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            return $res->ok() && $res->json('success') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}