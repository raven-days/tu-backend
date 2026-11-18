<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayananPengajuan extends Model
{
    protected $table = 'layanan_pengajuan';

    protected $fillable = ['slug', 'nama', 'deskripsi', 'akses', 'syarat', 'instruksi', 'wajib_lampiran', 'aktif', 'urutan'];

    protected $casts = ['wajib_lampiran' => 'boolean', 'aktif' => 'boolean'];

    public function tampil(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nama' => $this->nama,
            'deskripsi' => $this->deskripsi,
            'akses' => $this->akses,
            'syarat' => $this->syarat,
            'instruksi' => $this->instruksi,
            'wajib_lampiran' => $this->wajib_lampiran,
            'aktif' => $this->aktif,
            'urutan' => $this->urutan,
        ];
    }
}