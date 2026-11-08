<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_reset', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('username', 100);
            $t->string('catatan', 300)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('status', 20)->default('baru'); // baru | selesai | ditolak
            $t->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diproses_pada')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_reset');
    }
};