<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\YouTube;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
            // Несколько холодных контейнеров могут стартовать одновременно:
            // в Postgres держим advisory-lock, чтобы миграции не шли параллельно.
            $pg = DB::connection()->getDriverName() === 'pgsql';
            try {
                if ($pg) {
                    DB::select('select pg_advisory_lock(424242)');
                }
                Artisan::call('migrate', ['--force' => true]);
                if (! Schema::hasTable('settings') || Setting::count() === 0) {
                    (new DatabaseSeeder)->run();
                }
                touch($marker);
            } catch (Throwable $e) {
                report($e);
            } finally {
                if ($pg) {
                    rescue(fn () => DB::select('select pg_advisory_unlock(424242)'), report: false);
                }
            }
        }

        if (is_file($marker) && ! app()->runningUnitTests()) {
            defer(fn () => YouTube::syncIfStale());
        }

        return $next($request);
    }
}
