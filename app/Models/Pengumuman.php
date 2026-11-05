<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';
    protected $fillable = ['judul', 'kategori', 'tanggal', 'isi', 'gambar_path'];
    protected $casts = ['tanggal' => 'date'];

    public function tampil(): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'kategori' => $this->kategori,
            'tanggal' => $this->tanggal->format('Y-m-d'),
            'ringkasan' => Str::limit(trim(strip_tags($this->isi)), 140),
            'isi' => $this->isi,
            'gambar' => $this->gambar_path ? Storage::disk('public')->url($this->gambar_path) : null,
        ];
    }
}