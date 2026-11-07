<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner', function (Blueprint $t) {
            $t->id();
            $t->string('judul', 120)->nullable();
            $t->string('deskripsi', 255)->nullable();
            $t->string('tombol', 40)->nullable();
            $t->string('link', 255)->nullable();
            $t->string('gambar_path');
            $t->unsignedInteger('urutan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
            $t->index(['aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner');
    }
};