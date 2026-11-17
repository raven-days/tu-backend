<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lokasi_fasilitas', function (Blueprint $t) {
            $t->id();
            $t->string('kategori', 20);          // kelas | ruang | area
            $t->string('nama', 100);
            $t->string('keterangan', 200)->nullable();
            $t->boolean('aktif')->default(true);
            $t->unsignedInteger('urutan')->default(0);
            $t->timestamps();
            $t->unique(['kategori', 'nama']);
        });

        Schema::create('dokumentasi_fasilitas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lokasi_id')->constrained('lokasi_fasilitas')->restrictOnDelete();
            $t->date('tanggal')->index();
            $t->string('kebersihan', 15);        // baik | perhatian | perbaikan
            $t->string('keamanan', 15);
            $t->string('kelayakan', 15);
            $t->text('catatan')->nullable();
            $t->string('visibilitas', 10)->default('internal');   // internal | login | publik
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('oleh', 100)->nullable();
            $t->timestamps();
            $t->index(['lokasi_id', 'tanggal']);
        });

        Schema::create('dokumentasi_foto', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dokumentasi_id')->constrained('dokumentasi_fasilitas')->cascadeOnDelete();
            $t->string('path');
            $t->string('path_kecil');
            $t->string('keterangan', 200)->nullable();
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->unsignedInteger('ukuran')->default(0);
            $t->unsignedSmallInteger('lebar')->default(0);
            $t->unsignedSmallInteger('tinggi')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumentasi_foto');
        Schema::dropIfExists('dokumentasi_fasilitas');
        Schema::dropIfExists('lokasi_fasilitas');
    }
};