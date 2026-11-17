<?php

namespace App\Support;

use App\Models\Siswa;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Aturan dan olah data form surat, per peran (guru/tendik dan siswa). */
class FormSurat
{
    public const UNIT = ['Akademik', 'Kesiswaan & Humas', 'Sarpras & Adm', 'Tata Usaha'];

    public const JENIS = [
        'guru' => ['Surat Undangan', 'Surat Tugas', 'Surat Keterangan', 'SK & SOP'],
        'siswa' => ['Surat Tugas', 'Surat Keterangan', 'Surat Keterangan Peringkat'],
    ];

    public static function adaForm(?string $role): bool
    {
        return isset(self::JENIS[$role ?? '']);
    }

    /** Jenis surat yang wajib melampirkan dokumen pendukung. */
    public static function wajibLampiran(?string $role, string $jenis): bool
    {
        return ($role === 'guru' && in_array($jenis, ['Surat Tugas', 'SK & SOP'], true))
            || ($role === 'siswa' && $jenis === 'Surat Tugas');
    }

    public static function aturan(string $role, string $jenis): array
    {
        $nama = 'required|string|max:150';
        $nis = ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/'];
        $ya = 'required|in:ya,tidak';
        $tipe = [
            'data.tipe' => 'required|in:perorangan,kelompok',
            'data.penerima' => 'required|array|min:1|max:30',
        ];

        return match ($role . '|' . $jenis) {
            'guru|Surat Undangan' => [
                'data.pemohon' => $nama,
                'data.unit' => ['required', Rule::in(self::UNIT)],
                'data.acara' => 'required|string|max:200',
                'data.tujuan' => 'required|string|max:200',
                'data.tanggal_acara' => 'required|date',
                'data.waktu' => 'required|string|max:50',
                'data.tempat' => 'required|string|max:200',
            ],
            'guru|Surat Tugas' => [
                'data.pemohon' => $nama,
                'data.disetujui_kepsek' => $ya,
                ...$tipe,
                'data.penerima.*' => 'required|string|max:150|distinct',
            ],
            'guru|Surat Keterangan' => [
                'data.pemohon' => $nama,
                'data.keperluan' => 'required|string|min:5|max:500',
                'data.catatan' => 'nullable|string|max:1000',
            ],
            'guru|SK & SOP' => [
                'data.pemohon' => $nama,
                'data.unit' => ['required', Rule::in(self::UNIT)],
                'data.nama_dokumen' => 'required|string|max:200',
            ],
            'siswa|Surat Tugas' => [
                'data.pemohon' => $nama,
                'data.nis' => $nis,
                'data.tahu_pembina' => $ya,
                'data.nama_pembina' => 'required_if:data.tahu_pembina,ya|nullable|string|max:150',
                'data.tahu_wakasis' => $ya,
                'data.nama_wakasis' => 'required_if:data.tahu_wakasis,ya|nullable|string|max:150',
                ...$tipe,
                'data.penerima.*' => ['required', 'string', 'max:20', 'distinct', 'regex:/^[A-Za-z0-9.\-]+$/'],
                'data.kegiatan' => 'required|string|max:200',
                'data.penyelenggara' => 'required|string|max:200',
                'data.tanggal_kegiatan' => 'required|date',
                'data.tempat' => 'required|string|max:200',
            ],
            'siswa|Surat Keterangan' => [
                'data.pemohon' => 'nullable|string|max:150',
                'data.nis' => $nis,
                'data.keperluan' => 'required|string|min:5|max:500',
                'data.catatan' => 'nullable|string|max:1000',
            ],
            'siswa|Surat Keterangan Peringkat' => [
                'data.pemohon' => $nama,
                'data.nis' => $nis,
                'data.sudah_bertemu' => $ya,
                'data.nama_wakaka' => 'required_if:data.sudah_bertemu,ya|nullable|string|max:150',
                'data.keperluan' => 'required|string|min:5|max:500',
            ],
        };
    }

    /** Ubah hasil validasi jadi kolom tabel pengajuan. */
    public static function olah(string $role, string $jenis, array $v): array
    {
        $d = $v['data'];

        if (isset($d['penerima']) && ($d['tipe'] ?? '') === 'perorangan') {
            $d['penerima'] = array_slice(array_values($d['penerima']), 0, 1);
        }

        // Bersihkan nama pendamping bila jawabannya "tidak"
        foreach ([['tahu_pembina', 'nama_pembina'], ['tahu_wakasis', 'nama_wakasis'], ['sudah_bertemu', 'nama_wakaka']] as [$k, $n]) {
            if (($d[$k] ?? null) !== 'ya') {
                unset($d[$n]);
            }
        }

        if ($role === 'siswa') {
            $d = self::lengkapiSiswa($d);
        }

        $tanggal = now()->toDateString();
        $ket = null;

        switch ($role . '|' . $jenis) {
            case 'guru|Surat Undangan':
                $perihal = 'Undangan: ' . $d['acara'];
                $tanggal = $d['tanggal_acara'];
                break;
            case 'guru|Surat Tugas':
                $n = count($d['penerima']);
                $perihal = 'Surat Tugas untuk ' . $d['penerima'][0] . ($n > 1 ? ' dan ' . ($n - 1) . ' lainnya' : '');
                break;
            case 'guru|Surat Keterangan':
                $perihal = 'Keterangan: ' . $d['keperluan'];
                $ket = $d['catatan'] ?? null;
                break;
            case 'guru|SK & SOP':
                $perihal = 'Pengajuan SK/SOP: ' . $d['nama_dokumen'];
                break;
            case 'siswa|Surat Tugas':
                $perihal = 'Surat Tugas kegiatan: ' . $d['kegiatan'];
                $tanggal = $d['tanggal_kegiatan'];
                break;
            case 'siswa|Surat Keterangan':
                $perihal = 'Keterangan: ' . $d['keperluan'];
                $ket = $d['catatan'] ?? null;
                break;
            default: // siswa|Surat Keterangan Peringkat
                $perihal = 'Keterangan Peringkat: ' . $d['keperluan'];
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

    /** Cocokkan NIS dengan data siswa sekolah supaya petugas bisa memeriksa. */
    private static function lengkapiSiswa(array $d): array
    {
        if (!empty($d['nis'])) {
            $s = Siswa::where('nis', $d['nis'])->first();
            $d['terdata'] = (bool) $s;
            if ($s && $s->kelas) {
                $d['kelas'] = $s->kelas;
            }
        }

        if (!empty($d['penerima'])) {
            $peta = Siswa::whereIn('nis', $d['penerima'])->get()->keyBy('nis');
            $d['penerima_info'] = array_map(function ($n) use ($peta) {
                $s = $peta->get($n);

                return $s
                    ? "{$n} - {$s->nama}" . ($s->kelas ? " ({$s->kelas})" : '')
                    : "{$n} (tidak ada di data siswa)";
            }, array_values($d['penerima']));
        }

        return $d;
    }
}