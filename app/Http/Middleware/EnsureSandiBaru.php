<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSandiBaru
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->wajib_ganti_sandi) {
            return response()->json([
                'message' => 'Anda harus mengganti kata sandi sementara terlebih dahulu.',
                'wajib_ganti_sandi' => true,
            ], 403);
        }

        return $next($request);
    }
}