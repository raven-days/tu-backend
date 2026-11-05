<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('surat_keputusan', function (Blueprint $t) {
        $t->id();
        $t->string('nomor');
        $t->string('tentang');
        $t->unsignedSmallInteger('tahun');
        $t->string('kategori')->nullable();
        $t->string('file_path')->nullable();
        $t->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('surat_keputusan');
}
};
