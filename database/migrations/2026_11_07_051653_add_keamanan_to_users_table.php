<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true)->after('role');
            $t->unsignedSmallInteger('failed_login_attempts')->default(0)->after('is_active');
            $t->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $t->timestamp('last_login_at')->nullable()->after('locked_until');
            $t->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'is_active', 'failed_login_attempts', 'locked_until',
                'last_login_at', 'password_changed_at',
            ]);
        });
    }
};