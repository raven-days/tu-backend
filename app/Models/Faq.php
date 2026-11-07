<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    public const KATEGORI = ['Umum', 'Akun', 'Pengajuan', 'Dokumen'];

    protected $table = 'faq';

    protected $fillable = ['pertanyaan', 'jawaban', 'kategori', 'urutan', 'tampil'];

    protected function casts(): array
    {
        return ['tampil' => 'boolean', 'urutan' => 'integer'];
    }

    public function tampil(): array
    {
        return [
            'id' => $this->id,
            'pertanyaan' => $this->pertanyaan,
            'jawaban' => $this->jawaban,
            'kategori' => $this->kategori,
            'tampil' => $this->tampil,
        ];
    }
}