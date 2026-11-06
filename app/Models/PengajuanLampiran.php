<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanLampiran extends Model
{
    protected $table = 'pengajuan_lampiran';

    protected $fillable = ['pengajuan_id', 'nama_asli', 'path', 'ukuran'];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }
}