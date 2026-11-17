<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeputusan extends Model
{
    protected $table = 'surat_keputusan';
protected $fillable = ['jenis', 'nomor', 'tentang', 'penerbit', 'bidang', 'tahun', 'kategori', 'file_path'];
}