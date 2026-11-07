<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            // Cabut semua token supaya akun nonaktif langsung keluar di semua perangkat.
            $user->tokens()->delete();

            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi Tata Usaha.'], 401);
        }

        return $next($request);
    }
}