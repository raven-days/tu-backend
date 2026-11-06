<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanRiwayat extends Model
{
    protected $table = 'pengajuan_riwayat';

    protected $fillable = ['pengajuan_id', 'label', 'status', 'oleh', 'catatan', 'catatan_field'];

    protected $casts = ['catatan_field' => 'array'];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /** Contoh: "6 Nov 2026, 14:30" (WIB). */
    public function waktu(): string
    {
        return $this->created_at
            ->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('j M Y, H:i');
    }
}