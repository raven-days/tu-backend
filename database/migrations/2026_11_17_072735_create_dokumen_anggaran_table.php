<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_anggaran', function (Blueprint $t) {
            $t->id();
            $t->string('kategori', 12);            // regulasi | laporan
            $t->string('judul');
            $t->string('nomor', 100)->nullable();
            $t->string('sumber_dana', 10)->nullable(); // APBN | APBD | BOP | Lainnya
            $t->string('penerbit', 150)->nullable();
            $t->unsignedSmallInteger('tahun');
            $t->string('periode', 4)->nullable();   // TW1..TW4 (laporan)
            $t->string('akses', 10)->default('publik'); // publik | login
            $t->string('file_path')->nullable();
            $t->timestamps();
            $t->index(['kategori', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_anggaran');
    }
};