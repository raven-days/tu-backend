<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan', function (Blueprint $t) {
            $t->json('data')->nullable()->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan', fn (Blueprint $t) => $t->dropColumn('data'));
    }
};