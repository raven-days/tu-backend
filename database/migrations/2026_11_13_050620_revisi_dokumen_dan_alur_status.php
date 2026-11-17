<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_keputusan', function (Blueprint $t) {
            $t->string('jenis', 10)->default('sk')->after('id'); // sk | sop
            $t->string('penerbit', 150)->nullable()->after('tentang');
            $t->string('bidang', 100)->nullable()->after('penerbit');
            $t->index('jenis');
        });

        // Status lama "Disetujui" sekarang bernama "Tanda Tangan"
        DB::table('pengajuan')->where('status', 'Disetujui')->update(['status' => 'Tanda Tangan']);
    }

    public function down(): void
    {
        DB::table('pengajuan')->where('status', 'Tanda Tangan')->update(['status' => 'Disetujui']);

        Schema::table('surat_keputusan', function (Blueprint $t) {
            $t->dropIndex(['jenis']);
            $t->dropColumn(['jenis', 'penerbit', 'bidang']);
        });
    }
};