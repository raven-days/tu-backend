<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumentasiFoto extends Model
{
    protected $table = 'dokumentasi_foto';

    protected $fillable = ['dokumentasi_id', 'path', 'path_kecil', 'keterangan', 'urutan', 'ukuran', 'lebar', 'tinggi'];

    public function dokumentasi(): BelongsTo
    {
        return $this->belongsTo(DokumentasiFasilitas::class, 'dokumentasi_id');
    }
}