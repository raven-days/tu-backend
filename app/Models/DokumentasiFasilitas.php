<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class DokumentasiFasilitas extends Model
{
    protected $table = 'dokumentasi_fasilitas';

    public const NILAI = [
        'baik' => 'Baik',
        'perhatian' => 'Perlu perhatian',
        'perbaikan' => 'Perlu perbaikan',
    ];

    public const VISIBILITAS = [
        'internal' => 'Hanya staf TU dan admin',
        'login' => 'Semua pengguna yang login',
        'publik' => 'Publik (semua pengunjung)',
    ];

    protected $fillable = [
        'lokasi_id', 'tanggal', 'kebersihan', 'keamanan', 'kelayakan',
        'catatan', 'visibilitas', 'user_id', 'oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(LokasiFasilitas::class, 'lokasi_id');
    }

    public function foto(): HasMany
    {
        return $this->hasMany(DokumentasiFoto::class, 'dokumentasi_id')->orderBy('urutan')->orderBy('id');
    }

    /** Alamat foto (relatif). Entri yang tidak publik mendapat tanda tangan yang berlaku 30 menit. */
    public function urlFoto(DokumentasiFoto $f, string $ukuran): string
    {
        $param = ['foto' => $f->id, 'ukuran' => $ukuran];

        return $this->visibilitas === 'publik'
            ? route('fasilitas.foto', $param, false)
            : URL::temporarySignedRoute('fasilitas.foto', now()->addMinutes(30), $param, false);
    }

    public function tampil(?int $maksFoto = null): array
    {
        $foto = $this->foto;
        if ($maksFoto) {
            $foto = $foto->take($maksFoto);
        }

        return [
            'id' => $this->id,
            'lokasi' => $this->relationLoaded('lokasi') && $this->lokasi ? [
                'id' => $this->lokasi->id,
                'nama' => $this->lokasi->nama,
                'kategori' => $this->lokasi->kategori,
                'kategori_label' => $this->lokasi->labelKategori(),
            ] : null,
            'tanggal' => $this->tanggal->format('Y-m-d'),
            'tanggal_tampil' => $this->tanggal->copy()->locale('id')->translatedFormat('j M Y'),
            'kebersihan' => $this->kebersihan,
            'keamanan' => $this->keamanan,
            'kelayakan' => $this->kelayakan,
            'catatan' => $this->catatan,
            'visibilitas' => $this->visibilitas,
            'oleh' => $this->oleh,
            'jumlah_foto' => $this->foto->count(),
            'foto' => $foto->map(fn ($f) => [
                'id' => $f->id,
                'keterangan' => $f->keterangan,
                'kecil' => $this->urlFoto($f, 'kecil'),
                'besar' => $this->urlFoto($f, 'besar'),
            ])->values()->all(),
        ];
    }
}