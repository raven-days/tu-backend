<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Info sekolah di halaman Aset. Disimpan sebagai JSON di tabel pengaturan. */
class ProfilSekolah
{
    public const KUNCI = 'profil_sekolah';

    // Nilai awal bila admin belum pernah mengubahnya.
    public const DEFAULT = [
        'nama' => 'SMA Negeri 104 Jakarta',
        'npsn' => '20103291',
        'status' => 'Negeri',
        'jenjang' => 'SMA (Pendidikan Menengah)',
        'pendirian_mulai' => '17 Juli 1990',
        'pendirian_sk_nomor' => '0283/0/1991',
        'pendirian_sk_tanggal' => null,
        'akreditasi_peringkat' => 'A',
        'akreditasi_sk_nomor' => '1347/BAN-SM/SK/2021',
        'akreditasi_tahun' => '2021',
        'akreditasi_berlaku_sampai' => null,
        'akreditasi_lembaga' => 'Badan Akreditasi Nasional Sekolah/Madrasah (BAN-S/M)',
        'lokasi_alamat' => 'Jalan H. Taiman Barat, Kelurahan Gedong, Kecamatan Pasar Rebo, Jakarta Timur 13760',
        'lokasi_peta' => 'SMA Negeri 104 Jakarta, Jl. H. Taiman Barat, Gedong, Pasar Rebo, Jakarta Timur',
        'sumber_label' => 'Data Referensi Kemendikdasmen',
        'sumber_url' => 'https://referensi.data.kemendikdasmen.go.id/pendidikan/npsn/20103291',
    ];

    public static function ambil(): array
    {
        $json = DB::table('pengaturan')->where('kunci', self::KUNCI)->value('nilai');
        $tersimpan = $json ? (json_decode($json, true) ?: []) : [];

        return array_merge(self::DEFAULT, array_intersect_key($tersimpan, self::DEFAULT));
    }

    public static function simpan(array $data): array
    {
        $data = array_intersect_key($data, self::DEFAULT);

        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => self::KUNCI],
            ['nilai' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
        );

        return self::ambil();
    }
}