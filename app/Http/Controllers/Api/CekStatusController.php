<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;

class CekStatusController extends Controller
{
    public function show(string $nomor)
    {
        $p = Pengajuan::with(['user', 'riwayat'])->where('nomor', strtoupper(trim($nomor)))->first();

        if (!$p) {
            return response()->json(['message' => 'Nomor pengajuan tidak ditemukan.'], 404);
        }

        return response()->json([
            'id' => $p->nomor,
            'jenis' => $p->jenis,
            'status' => $p->status,
            'pemohon' => $this->samarkan($p->user?->name),
            'pengambilan' => in_array($p->status, ['Disetujui', 'Selesai'], true) ? $p->ringkasPengambilan() : null,
            'history' => $p->riwayat->map(fn ($r) => [
                'label' => $r->label,
                'status' => $r->status,
                'time' => $r->waktu(),
            ])->values(),
        ]);
    }

    private function samarkan(?string $nama): string
    {
        $kata = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn ($w) => mb_substr($w, 0, 1) . '***', $kata));
    }
}