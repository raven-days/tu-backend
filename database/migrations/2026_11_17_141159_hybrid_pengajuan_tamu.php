<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id boleh kosong untuk pengajuan tamu
        Schema::table('pengajuan', fn (Blueprint $t) => $t->dropForeign(['user_id']));
        Schema::table('pengajuan', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->change());
        Schema::table('pengajuan', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->string('jalur', 10)->default('akun')->after('user_id'); // akun | tamu
            $t->string('pin_hash')->nullable();
            $t->unsignedBigInteger('layanan_id')->nullable();
            $t->string('ip_hash', 64)->nullable();
        });

        Schema::table('pengajuan_lampiran', function (Blueprint $t) {
            $t->string('mime', 50)->nullable();
            $t->boolean('terenkripsi')->default(false);
            $t->timestamp('dihapus_pada')->nullable();
        });

        Schema::create('layanan_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('nama', 150);
            $t->text('deskripsi')->nullable();
            $t->string('akses', 10)->default('guest'); // guest | login | manual
            $t->text('syarat')->nullable();
            $t->text('instruksi')->nullable();
            $t->boolean('wajib_lampiran')->default(true);
            $t->boolean('aktif')->default(true);
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();
        });

        $now = now();
        $baris = [
            ['surat-keterangan-sederhana', 'Permohonan Surat Keterangan Sederhana', 'Surat keterangan untuk keperluan umum.', 'guest', "Foto KTP/Kartu Pelajar pemohon.\nSurat dibuat setelah data diperiksa petugas TU.", null, true],
            ['legalisasi-dokumen', 'Permohonan Legalisasi Dokumen', 'Legalisasi ijazah, SKHUN, atau dokumen sekolah lainnya.', 'guest', "Foto dokumen yang akan dilegalisasi.\nDokumen asli wajib dibawa saat pengambilan.", null, true],
            ['informasi-administrasi', 'Permintaan Informasi Administrasi', 'Pertanyaan umum seputar administrasi sekolah.', 'guest', null, null, false],
            ['perubahan-data-siswa', 'Perubahan Data Pribadi Siswa', 'Koreksi nama, tanggal lahir, atau data pribadi siswa.', 'manual', null, 'Perubahan data pribadi harus diperiksa langsung. Datanglah ke Tata Usaha dengan membawa dokumen asli (akta kelahiran, KK) dan surat permohonan dari orang tua/wali.', true],
            ['layanan-kepegawaian', 'Layanan Kepegawaian', 'Layanan untuk guru dan tenaga kependidikan.', 'login', null, null, true],
            ['dokumen-pribadi-siswa', 'Permintaan Dokumen Pribadi Siswa', 'Salinan rapor, ijazah, atau dokumen pribadi siswa.', 'manual', null, 'Dokumen pribadi hanya diberikan kepada siswa, orang tua/wali, atau pihak yang berwenang. Datanglah ke Tata Usaha dengan identitas dan surat kuasa bila diwakilkan.', true],
        ];
        foreach ($baris as $i => [$slug, $nama, $desk, $akses, $syarat, $ins, $wajib]) {
            DB::table('layanan_pengajuan')->insert([
                'slug' => $slug, 'nama' => $nama, 'deskripsi' => $desk, 'akses' => $akses,
                'syarat' => $syarat, 'instruksi' => $ins, 'wajib_lampiran' => $wajib,
                'aktif' => true, 'urutan' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan_pengajuan');
        Schema::table('pengajuan_lampiran', fn (Blueprint $t) => $t->dropColumn(['mime', 'terenkripsi', 'dihapus_pada']));
        Schema::table('pengajuan', fn (Blueprint $t) => $t->dropColumn(['jalur', 'pin_hash', 'layanan_id', 'ip_hash']));
    }
};