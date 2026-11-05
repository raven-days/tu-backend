<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class PegawaiImporter
{
    /** Baca file Excel menjadi daftar baris pegawai. */
    public function baca(string $path): array
    {
        $book = IOFactory::load($path);
        $hasil = [];

        foreach ($book->getWorksheetIterator() as $sheet) {
            $judul = strtolower($sheet->getTitle());
            $kategori = (str_contains($judul, 'tendik') || str_contains($judul, 'kependidikan'))
                ? 'tendik'
                : (str_contains($judul, 'guru') ? 'guru' : null);
            if (!$kategori) {
                continue;
            }

            $rows = $sheet->toArray(null, true, true, false);

            // cari baris judul yang berisi kolom NAMA
            $h = null;
            foreach ($rows as $i => $r) {
                foreach ($r as $c) {
                    if (strtoupper(trim((string) $c)) === 'NAMA') {
                        $h = $i;
                        break 2;
                    }
                }
            }
            if ($h === null) {
                throw new RuntimeException("Sheet \"{$sheet->getTitle()}\": kolom NAMA tidak ditemukan.");
            }

            $iNama = $iJab = null;
            foreach ($rows[$h] as $k => $c) {
                $c = strtoupper(trim((string) $c));
                if ($c === 'NAMA') $iNama = $k;
                if ($c === 'JABATAN') $iJab = $k;
            }

            $urutan = 0;
            foreach (array_slice($rows, $h + 1) as $r) {
                $nama = preg_replace('/\s+/', ' ', trim((string) ($r[$iNama] ?? '')));
                if ($nama === '') continue;
                $jabatan = preg_replace('/\s+/', ' ', trim((string) ($iJab !== null ? ($r[$iJab] ?? '') : '')));

                $hasil[] = [
                    'nama' => $nama,
                    'jabatan' => $jabatan,
                    'kategori' => $kategori,
                    'urutan' => ++$urutan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        return $hasil;
    }

    /** Ganti seluruh isi tabel pegawai dengan isi file. Mengembalikan jumlah guru dan tendik. */
    public function impor(string $path): array
    {
        $hasil = $this->baca($path);

        if (!$hasil) {
            throw new RuntimeException('Tidak ada data terbaca. Pastikan nama sheet memuat kata "Guru" atau "Tendik".');
        }

        DB::transaction(function () use ($hasil) {
            Pegawai::query()->delete();
            Pegawai::insert($hasil);
        });

        $guru = count(array_filter($hasil, fn ($r) => $r['kategori'] === 'guru'));

        return ['guru' => $guru, 'tendik' => count($hasil) - $guru];
    }
}