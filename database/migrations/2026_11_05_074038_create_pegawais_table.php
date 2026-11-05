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
    Schema::create('pegawai', function (Blueprint $t) {
        $t->id();
        $t->string('nama');
        $t->string('jabatan')->nullable();
        $t->enum('kategori', ['guru', 'tendik']);
        $t->unsignedInteger('urutan')->default(0);
        $t->timestamps();
        $t->index(['kategori', 'urutan']);
    });
}

public function down(): void
{
    Schema::dropIfExists('pegawai');
}
};
