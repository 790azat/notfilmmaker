<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * По просьбе владельца (2026-09-30) вместо дефолтного админа снова работает
     * «первый зарегистрированный становится админом». Удаляем admin@notfilmmaker.am,
     * только если его временный пароль ещё не меняли.
     */
    public function up(): void
    {
        $admin = DB::table('users')->where('email', 'admin@notfilmmaker.am')->first();
        if (! $admin || ! Hash::check('notfilm2026', $admin->password)) {
            return;
        }

        DB::transaction(function () use ($admin) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
            DB::table('users')->where('id', $admin->id)->delete();
        });
    }
};
