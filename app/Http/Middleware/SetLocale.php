<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = array_keys(config('app.locales'));
        $locale = $request->session()->get('locale');

        if (! in_array($locale, $locales, true)) {
            $locale = $request->getPreferredLanguage($locales) ?: config('app.locale');
            // Браузер без армянского/русского/английского — показываем армянский.
            if (! in_array($locale, $locales, true)) {
                $locale = config('app.locale');
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
