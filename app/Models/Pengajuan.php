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
        'keterangan', 'status', 'catatan_field', 'pengambilan', 'revisi',
    ];

    protected $casts = [
        'tanggal_dibutuhkan' => 'date',
        'catatan_field' => 'array',
        'pengambilan' => 'array',
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
        return $this->hasMany(PengajuanLampiran::class)->where('jenis', 'pemohon')->orderBy('id');
    }

    /** Dokumen hasil yang diunggah petugas TU. */
    public function hasil(): HasMany
    {
        return $this->hasMany(PengajuanLampiran::class)->where('jenis', 'hasil')->orderBy('id');
    }

    /** Petunjuk pengambilan dalam satu kalimat, atau null bila belum diisi. */
    public function ringkasPengambilan(): ?string
    {
        $p = $this->pengambilan;

        if (!is_array($p) || empty($p['mode'])) {
            return null;
        }

        $catatan = !empty($p['catatan']) ? ' ' . $p['catatan'] : '';

        if ($p['mode'] === 'unduh') {
            return 'Hasil dapat diunduh pada halaman pengajuan ini.' . $catatan;
        }

        $teks = 'Ambil hasil di ' . (!empty($p['lokasi']) ? $p['lokasi'] : 'Kantor Tata Usaha');

        if (!empty($p['tanggal'])) {
            $teks .= ', ' . \Illuminate\Support\Carbon::parse($p['tanggal'])->locale('id')->translatedFormat('l, j F Y');
        }

        if (!empty($p['jam_mulai'])) {
            $teks .= ', pukul ' . str_replace(':', '.', $p['jam_mulai']);
            if (!empty($p['jam_selesai'])) {
                $teks .= ' - ' . str_replace(':', '.', $p['jam_selesai']);
            }
            $teks .= ' WIB';
        }

        return $teks . '.' . $catatan;
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

            $data['hasil'] = $this->hasil->map(fn ($l) => [
                'id' => $l->id,
                'nama' => $l->nama_asli,
                'path' => '/pengajuan/lampiran/' . $l->id,
            ])->values()->all();

            $data['pengambilan'] = $this->ringkasPengambilan()
                ? ($this->pengambilan + ['ringkas' => $this->ringkasPengambilan()])
                : null;

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