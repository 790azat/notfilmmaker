<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * После переезда на свой домен старый адрес (например notfilmmaker.vercel.app)
 * отдаёт 301 на тот же путь на APP_URL, чтобы поисковики и старые ссылки переходили туда.
 * Включается переменной REDIRECT_TO_APP_URL=true. Крон и вебхук Telegram не трогаются.
 */
class RedirectToAppUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $target = rtrim((string) config('app.url'), '/');
        $host = parse_url($target, PHP_URL_HOST);

        if (config('app.redirect_to_app_url') && $host && strcasecmp($request->getHost(), $host) !== 0
            && $request->isMethodSafe() && ! $request->is('cron/*', 'telegram/*', 'up')) {
            return redirect()->away($target.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
