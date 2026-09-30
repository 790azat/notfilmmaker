<?php

/*
 * Сборка фронтенда (Vite) для функции Vercel.
 * Рантайм vercel-php упаковывает в функцию исходники, и без public/build/manifest.json
 * любая страница с @vite падала с 500. Вызывается из `composer run vercel`.
 */

if (is_file(__DIR__.'/../public/build/manifest.json')) {
    exit(0);
}

passthru('npm ci --no-audit --no-fund && npm run build', $code);

exit($code);
