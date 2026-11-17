<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiFasilitas extends Model
{
    protected $table = 'lokasi_fasilitas';

    public const KATEGORI = [
        'kelas' => 'Ruang kelas',
        'ruang' => 'Ruang dan fasilitas fungsional',
        'area' => 'Area dan fasilitas',
    ];

    protected $fillable = ['kategori', 'nama', 'keterangan', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function dokumentasi(): HasMany
    {
        return $this->hasMany(DokumentasiFasilitas::class, 'lokasi_id');
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }
}