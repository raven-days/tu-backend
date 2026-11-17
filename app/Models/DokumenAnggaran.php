<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenAnggaran extends Model
{
    protected $table = 'dokumen_anggaran';

    protected $fillable = [
        'kategori', 'judul', 'nomor', 'sumber_dana', 'penerbit',
        'tahun', 'periode', 'akses', 'file_path',
    ];
}