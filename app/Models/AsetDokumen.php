<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsetDokumen extends Model
{
    public const PERAN = ['siswa', 'guru', 'tu'];

    protected $table = 'aset_dokumen';

    protected $fillable = [
        'judul', 'kategori', 'nomor', 'keterangan',
        'file_path', 'file_nama', 'file_mime', 'file_ukuran', 'peran',
    ];

    protected function casts(): array
    {
        return [
            'peran' => 'array',
            'file_ukuran' => 'integer',
        ];
    }

    public function scopeUrut($q)
    {
        return $q->orderBy('kategori')->orderBy('judul')->orderBy('id');
    }

    /** Pengelola (izin aset) melihat semuanya; peran lain hanya yang dicantumkan. */
    public function bisaDilihat(User $user): bool
    {
        return $user->punyaIzin('aset') || in_array($user->role, $this->peran ?? [], true);
    }

    public function tampil(bool $admin = false): array
    {
        $data = [
            'id' => $this->id,
            'judul' => $this->judul,
            'kategori' => $this->kategori,
            'nomor' => $this->nomor,
            'keterangan' => $this->keterangan,
            'file_nama' => $this->file_nama,
            'file_ukuran' => $this->file_ukuran,
            'tipe' => $this->file_mime,
            'diperbarui' => $this->updated_at?->toIso8601String(),
        ];

        if ($admin) {
            $data['peran'] = $this->peran ?? [];
        }

        return $data;
    }
}