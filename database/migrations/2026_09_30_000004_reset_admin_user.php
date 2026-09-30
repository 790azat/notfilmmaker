<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Сброс пользователей по просьбе владельца (2026-09-30): все аккаунты и сессии удаляются,
     * остаётся один админ admin@notfilmmaker.am с временным паролем, который владелец сменит в профиле.
     * В коде лежит только хеш пароля.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('sessions')->delete();
            DB::table('password_reset_tokens')->delete();
            DB::table('users')->delete();
            DB::table('users')->insert([
                'name' => 'Admin',
                'email' => 'admin@notfilmmaker.am',
                'password' => '$2y$12$WREZMm.DHFnHSR3UyQpAL.0VfyHlMc/pxTsOUZ.yYAzIxdzHkhQaW',
                'is_admin' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
