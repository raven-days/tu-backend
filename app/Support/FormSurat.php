<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Aturan dan olah data untuk form surat guru/tendik. */
class FormSurat
{
    public const UNIT = ['Akademik', 'Kesiswaan & Humas', 'Sarpras & Adm', 'Tata Usaha'];
    public const JENIS = ['Surat Undangan', 'Surat Tugas', 'Surat Keterangan'];

    public static function aturan(string $jenis): array
    {
        $nama = 'required|string|max:150';

        return match ($jenis) {
            'Surat Undangan' => [
                'data.pemohon' => $nama,
                'data.unit' => ['required', Rule::in(self::UNIT)],
                'data.acara' => 'required|string|max:200',
                'data.tujuan' => 'required|string|max:200',
                'data.tanggal_acara' => 'required|date',
                'data.waktu' => 'required|string|max:50',
                'data.tempat' => 'required|string|max:200',
            ],
            'Surat Tugas' => [
                'data.pemohon' => $nama,
                'data.disetujui_kepsek' => 'required|in:ya,tidak',
                'data.tipe' => 'required|in:perorangan,kelompok',
                'data.penerima' => 'required|array|min:1|max:30',
                'data.penerima.*' => 'required|string|max:150|distinct',
            ],
            default => [ // Surat Keterangan
                'data.pemohon' => $nama,
                'data.keperluan' => 'required|string|min:5|max:500',
                'data.catatan' => 'required|string|max:1000',
            ],
        };
    }

    /** Ubah hasil validasi jadi kolom tabel pengajuan. */
    public static function olah(string $jenis, array $v): array
    {
        $d = $v['data'];

        if ($jenis === 'Surat Tugas' && $d['tipe'] === 'perorangan') {
            $d['penerima'] = array_slice(array_values($d['penerima']), 0, 1);
        }

        switch ($jenis) {
            case 'Surat Undangan':
                $perihal = 'Undangan: ' . $d['acara'];
                $tanggal = $d['tanggal_acara'];
                $ket = null;
                break;
            case 'Surat Tugas':
                $n = count($d['penerima']);
                $perihal = 'Surat Tugas untuk ' . $d['penerima'][0] . ($n > 1 ? ' dan ' . ($n - 1) . ' lainnya' : '');
                $tanggal = now()->toDateString();
                $ket = null;
                break;
            default:
                $perihal = 'Keterangan: ' . $d['keperluan'];
                $tanggal = now()->toDateString();
                $ket = $d['catatan'];
        }

        return [
            'jenis' => $jenis,
            'perihal' => Str::limit($perihal, 250, ''),
            'tanggal' => $tanggal,
            'keterangan' => $ket,
            'data' => $d,
            'lampiran_tetap' => $v['lampiran_tetap'] ?? [],
        ];
    }
}