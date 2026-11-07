<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontakSekolah extends Model
{
    protected $table = 'kontak_sekolah';

    protected $fillable = [
        'alamat', 'telepon', 'email', 'whatsapp', 'maps_url',
        'jam_layanan', 'media_sosial', 'bagian',
    ];

    protected function casts(): array
    {
        return [
            'jam_layanan' => 'array',
            'media_sosial' => 'array',
            'bagian' => 'array',
        ];
    }

    /** Bentuk data yang dikirim ke website. */
    public function tampil(): array
    {
        return [
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'maps_url' => $this->maps_url,
            'jam_layanan' => $this->jam_layanan ?? [],
            'media_sosial' => $this->media_sosial ?? [],
            'bagian' => $this->bagian ?? [],
        ];
    }
}