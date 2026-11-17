<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $t) {
            $t->id();
            $t->string('nis', 20)->unique();
            $t->string('nama', 150);
            $t->string('kelas', 20)->nullable();
            $t->timestamps();
            $t->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};