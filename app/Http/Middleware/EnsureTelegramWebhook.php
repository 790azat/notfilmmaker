<?php

namespace App\Http\Middleware;

use App\Support\Telegram;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Подключает Telegram-бота из переменной окружения, см. Telegram::ensureWebhook(). */
class EnsureTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            Telegram::ensureWebhook($request->getHost());
        }

        return $next($request);
    }
}
