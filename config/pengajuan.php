<?php

return [
    'tamu' => [
        'ip_per_jam'       => (int) env('TAMU_IP_PER_JAM', 10),   // pengajuan per IP per jam
        'wa_per_hari'      => (int) env('TAMU_WA_PER_HARI', 5),   // pengajuan per nomor WA per hari
        'jeda_ganda_menit' => 10,                                  // pengajuan sama dalam X menit ditolak
        'cek_gagal_maks'   => 5,                                   // salah PIN per kode
        'cek_ip_maks'      => 20,                                  // salah PIN per IP
        'cek_kunci_menit'  => 15,
        'maks_file'        => 3,
        'maks_kb'          => 5120,    // 5 MB per file
        'maks_total_kb'    => 15360,   // 15 MB total
        'maks_megapiksel'  => 24,
    ],
    'retensi' => [
        'ditolak_hari' => (int) env('RETENSI_DITOLAK_HARI', 30),
        'selesai_hari' => (int) env('RETENSI_SELESAI_HARI', 90),
    ],
];