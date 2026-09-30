<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * На Vercel нет консоли, поэтому миграции запускаются сами при первом запросе
 * к новому деплою (один раз на контейнер; повторный запуск ничего не меняет).
 */
class AutoMigrate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.auto_migrate')) {
            return $next($request);
        }

        $marker = sys_get_temp_dir().'/migrated-'.md5(implode(',', glob(database_path('migrations/*.php'))));
        if (! is_file($marker)) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                if (! Schema::hasTable('settings') || Setting::count() === 0) {
                    (new DatabaseSeeder)->run();
                }
                touch($marker);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
