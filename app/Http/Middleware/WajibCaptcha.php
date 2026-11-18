<?php

namespace App\Http\Middleware;

use App\Support\Captcha;
use Closure;
use Illuminate\Http\Request;

class WajibCaptcha
{
    public function handle(Request $request, Closure $next)
    {
        if (!Captcha::lolos($request)) {
            return response()->json(['message' => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.'], 422);
        }

        return $next($request);
    }
}