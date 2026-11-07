<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::orderByDesc('id');

        if ($aksi = $request->query('aksi')) {
            $q->where('aksi', $aksi);
        }

        return $q->paginate(30)->through(fn ($l) => [
            'id' => $l->id,
            'waktu' => $l->created_at
                ->copy()
                ->timezone('Asia/Jakarta')
                ->locale('id')
                ->translatedFormat('j M Y, H:i'),
            'username' => $l->username ?? '-',
            'aksi' => $l->aksi,
            'keterangan' => $l->keterangan ?? '',
            'ip' => $l->ip_address ?? '-',
        ]);
    }
}