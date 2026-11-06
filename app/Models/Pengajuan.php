<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengajuan extends Model
{
    protected $table = 'pengajuan';

    protected $fillable = [
        'nomor', 'user_id', 'jenis', 'perihal', 'tanggal_dibutuhkan',
        'keterangan', 'status', 'catatan_field', 'revisi',
    ];

    protected $casts = [
        'tanggal_dibutuhkan' => 'date',
        'catatan_field' => 'array',
    ];

    /** Perubahan status yang diizinkan (oleh petugas TU). */
    public const TRANSISI = [
        'Menunggu' => ['Diverifikasi', 'Perlu Perbaikan', 'Ditolak'],
        'Diverifikasi' => ['Disetujui', 'Perlu Perbaikan', 'Ditolak'],
        'Disetujui' => ['Selesai'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(PengajuanRiwayat::class)->orderBy('id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(PengajuanLampiran::class)->orderBy('id');
    }

    /** Tambah satu baris ke riwayat status. */
    public function catat(string $label, string $status, ?string $oleh, ?string $catatan = null, ?array $catatanField = null): void
    {
        $this->riwayat()->create([
            'label' => $label,
            'status' => $status,
            'oleh' => $oleh,
            'catatan' => $catatan,
            'catatan_field' => $catatanField,
        ]);
    }

    /** Bentuk data yang dikirim ke website. */
    public function tampil(bool $detail = true): array
    {
        $data = [
            'id' => $this->nomor,
            'jenis' => $this->jenis,
            'perihal' => $this->perihal,
            'tanggal' => $this->tanggal_dibutuhkan->format('Y-m-d'),
            'keterangan' => $this->keterangan ?? '',
            'pemohon' => $this->user?->name,
            'ownerRole' => $this->user?->role,
            'status' => $this->status,
            'revisi' => $this->revisi,
            'fieldNotes' => (object) ($this->catatan_field ?? []),
        ];

        if ($detail) {
            $data['lampiran'] = $this->lampiran->map(fn ($l) => [
                'id' => $l->id,
                'nama' => $l->nama_asli,
                'path' => '/pengajuan/lampiran/' . $l->id,
            ])->values()->all();

            $data['history'] = $this->riwayat->map(fn ($r) => [
                'label' => $r->label,
                'status' => $r->status,
                'time' => $r->waktu(),
                'by' => $r->oleh,
                'note' => $r->catatan ?? '',
                'fieldNotes' => (object) ($r->catatan_field ?? []),
            ])->values()->all();
        }

        return $data;
    }
}