<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIzin
{
    /** Pemakaian di route: izin:sk  atau  izin:sk,pegawai (cukup punya salah satu). */
    public function handle(Request $request, Closure $next, string ...$modul): Response
    {
        $user = $request->user();

        foreach ($modul as $m) {
            if ($user && $user->punyaIzin($m)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Anda tidak memiliki izin untuk modul ini.'], 403);
    }
}