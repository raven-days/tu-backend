<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false)->after('role');
            $t->json('izin')->nullable()->after('is_admin');
        });

        // Akun TU yang sudah ada tetap berkuasa penuh seperti sebelumnya.
        DB::table('users')->where('role', 'tu')->update([
            'is_admin' => true,
            'izin' => json_encode(['pengajuan', 'sk', 'pegawai', 'pengumuman']),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['is_admin', 'izin']);
        });
    }
};