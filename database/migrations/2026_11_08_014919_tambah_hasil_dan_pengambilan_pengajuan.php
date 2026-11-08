<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_lampiran', function (Blueprint $t) {
            // 'pemohon' = dokumen dari pemohon, 'hasil' = dokumen hasil dari petugas TU
            $t->string('jenis', 10)->default('pemohon')->after('pengajuan_id');
        });

        Schema::table('pengajuan', function (Blueprint $t) {
            // Petunjuk pengambilan hasil: mode, tanggal, jam_mulai, jam_selesai, lokasi, catatan
            $t->json('pengambilan')->nullable()->after('catatan_field');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan', fn (Blueprint $t) => $t->dropColumn('pengambilan'));
        Schema::table('pengajuan_lampiran', fn (Blueprint $t) => $t->dropColumn('jenis'));
    }
};