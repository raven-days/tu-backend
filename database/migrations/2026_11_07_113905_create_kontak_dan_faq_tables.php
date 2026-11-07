<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontak_sekolah', function (Blueprint $t) {
            $t->id();
            $t->text('alamat')->nullable();
            $t->string('telepon', 50)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('whatsapp', 20)->nullable();   // format internasional tanpa "+", mis. 6281234567890
            $t->string('maps_url', 500)->nullable();
            $t->json('jam_layanan')->nullable();      // [{hari, jam, keterangan}]
            $t->json('media_sosial')->nullable();     // [{nama, url}]
            $t->json('bagian')->nullable();           // opsional: [{nama, telepon, email, keterangan}]
            $t->timestamps();
        });

        Schema::create('faq', function (Blueprint $t) {
            $t->id();
            $t->string('pertanyaan', 255);
            $t->text('jawaban');
            $t->string('kategori', 50)->default('Umum');
            $t->unsignedInteger('urutan')->default(0)->index();
            $t->boolean('tampil')->default(true);
            $t->timestamps();
        });

        $sekarang = now();
        $json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE);

        // Data awal: disalin dari data yang sudah ada di situs (admin menggantinya lewat dashboard).
        DB::table('kontak_sekolah')->insert([
            'alamat' => 'Jalan H. Taiman Barat, Kelurahan Gedong, Kecamatan Pasar Rebo, Kode Pos 13760, Jakarta Timur',
            'telepon' => '(021) 8408247',
            'email' => 'infosman104@gmail.com',
            'whatsapp' => null,
            'maps_url' => null,
            'jam_layanan' => $json([['hari' => '', 'jam' => '06.30 - 15.00 WIB', 'keterangan' => '']]),
            'media_sosial' => $json([]),
            'bagian' => $json([]),
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ]);

        $faq = [
            ['Pengajuan', 'Bagaimana cara mengajukan layanan?',
                "1. Masuk ke akun Anda.\n2. Buka menu Ajukan Layanan, lalu pilih jenis layanan.\n3. Isi formulir dan unggah lampiran bila diperlukan.\n4. Kirim pengajuan. Anda akan mendapat nomor pengajuan untuk memantau prosesnya."],
            ['Pengajuan', 'Bagaimana cara memeriksa status pengajuan?',
                "Buka menu Cek Status, lalu masukkan nomor pengajuan Anda (contoh: TU-2026-00128). Anda tidak perlu masuk untuk melihat status. Setelah masuk, seluruh pengajuan Anda juga tersedia di halaman Pengajuan Saya."],
            ['Pengajuan', 'Apa arti status "Perlu Perbaikan"?',
                "Petugas menemukan data atau lampiran yang perlu dilengkapi. Buka detail pengajuan, baca catatan dari petugas, perbaiki bagian yang ditandai, lalu kirim ulang."],
            ['Dokumen', 'Berkas apa yang boleh dilampirkan?',
                "Format PDF, JPG, atau PNG dengan ukuran maksimal 2 MB per berkas. Jumlah lampiran dibatasi pada setiap pengajuan."],
            ['Dokumen', 'Apakah Surat Keputusan (SK) bisa dilihat tanpa masuk?',
                "Bisa. Halaman Surat Keputusan terbuka untuk semua pengguna tanpa perlu masuk."],
            ['Akun', 'Siapa yang membuat akun saya?',
                "Akun dibuat dan dikelola oleh Tata Usaha sekolah. Jika Anda belum memiliki akun, hubungi Tata Usaha melalui kontak di halaman ini."],
            ['Akun', 'Saya lupa kata sandi. Apa yang harus dilakukan?',
                "Hubungi Tata Usaha melalui kontak di halaman ini untuk meminta reset kata sandi. Setelah masuk dengan sandi sementara, segera ganti lewat menu Akun, lalu Ganti kata sandi."],
        ];

        foreach ($faq as $i => [$kategori, $tanya, $jawab]) {
            DB::table('faq')->insert([
                'pertanyaan' => $tanya,
                'jawaban' => $jawab,
                'kategori' => $kategori,
                'urutan' => $i + 1,
                'tampil' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faq');
        Schema::dropIfExists('kontak_sekolah');
    }
};