<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan', function (Blueprint $t) {
            $t->id();
            $t->string('nomor', 30)->nullable()->unique();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('jenis', 100);
            $t->string('perihal');
            $t->date('tanggal_dibutuhkan');
            $t->text('keterangan')->nullable();
            $t->string('status', 30)->default('Menunggu');
            $t->json('catatan_field')->nullable();
            $t->unsignedInteger('revisi')->default(0);
            $t->timestamps();
            $t->index('status');
        });

        Schema::create('pengajuan_riwayat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $t->string('label', 50);
            $t->string('status', 30);
            $t->string('oleh')->nullable();
            $t->text('catatan')->nullable();
            $t->json('catatan_field')->nullable();
            $t->timestamps();
        });

        Schema::create('pengajuan_lampiran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengajuan_id')->constrained('pengajuan')->cascadeOnDelete();
            $t->string('nama_asli');
            $t->string('path');
            $t->unsignedInteger('ukuran')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_lampiran');
        Schema::dropIfExists('pengajuan_riwayat');
        Schema::dropIfExists('pengajuan');
    }
};