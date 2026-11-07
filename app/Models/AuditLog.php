<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    // Log tidak pernah diubah, jadi tidak ada kolom updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'username', 'aksi', 'keterangan',
        'target_tipe', 'target_id', 'ip_address', 'user_agent',
    ];

    /**
     * Catat satu kejadian. Kalau $user kosong, dipakai pengguna yang sedang login.
     * Kegagalan mencatat tidak boleh menggagalkan aksi utama.
     */
    public static function catat(
        string $aksi,
        ?string $keterangan = null,
        ?User $user = null,
        ?string $targetTipe = null,
        ?int $targetId = null,
    ): void {
        try {
            $user ??= request()->user();

            static::create([
                'user_id' => $user?->id,
                'username' => $user?->username,
                'aksi' => $aksi,
                'keterangan' => $keterangan,
                'target_tipe' => $targetTipe,
                'target_id' => $targetId,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}