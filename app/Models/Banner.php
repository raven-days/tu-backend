<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    protected $table = 'banner';

    protected $fillable = ['judul', 'deskripsi', 'tombol', 'link', 'gambar_path', 'urutan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function tampil(bool $admin = false): array
    {
        $data = [
            'id' => $this->id,
            'judul' => $this->judul,
            'deskripsi' => $this->deskripsi,
            'tombol' => $this->tombol,
            'link' => $this->link,
            'gambar' => Storage::disk('public')->url($this->gambar_path),
        ];

        if ($admin) {
            $data['aktif'] = $this->aktif;
            $data['urutan'] = $this->urutan;
        }

        return $data;
    }
}