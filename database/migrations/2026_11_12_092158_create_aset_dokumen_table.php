<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset_dokumen', function (Blueprint $t) {
            $t->id();
            $t->string('judul', 150);
            $t->string('kategori', 60)->nullable();
            $t->string('nomor', 100)->nullable();
            $t->string('keterangan', 255)->nullable();
            $t->string('file_path');
            $t->string('file_nama', 150);
            $t->string('file_mime', 100);
            $t->unsignedInteger('file_ukuran')->default(0);
            $t->json('peran')->nullable(); // ["siswa","guru","tu"]
            $t->timestamps();
            $t->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_dokumen');
    }
};