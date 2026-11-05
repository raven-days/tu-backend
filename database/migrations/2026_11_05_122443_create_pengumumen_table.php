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
    Schema::create('pengumuman', function (Blueprint $t) {
        $t->id();
        $t->string('judul');
        $t->string('kategori')->default('Informasi');
        $t->date('tanggal');
        $t->text('isi');
        $t->string('gambar_path')->nullable();
        $t->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('pengumuman');
}
};
