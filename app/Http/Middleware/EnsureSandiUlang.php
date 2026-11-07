<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\KonfirmasiSandiController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSandiUlang
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (!$token || !Cache::has(KonfirmasiSandiController::kunci($request))) {
            // 428 = "Precondition Required": frontend akan menampilkan kotak konfirmasi sandi.
            return response()->json([
                'message' => 'Konfirmasi kata sandi diperlukan.',
                'perlu_sandi' => true,
            ], 428);
        }

        return $next($request);
    }
}