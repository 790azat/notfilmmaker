<?php

/*
 * Точка входа для Vercel (runtime vercel-php).
 * Файловая система Vercel доступна на запись только в /tmp,
 * поэтому все кэши, скомпилированные шаблоны и storage уходят туда.
 */

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'APP_CONFIG_CACHE' => '/tmp/cache/config.php',
    'APP_EVENTS_CACHE' => '/tmp/cache/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/cache/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/cache/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/cache/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'database',
    'QUEUE_CONNECTION' => 'sync',
    'AUTO_MIGRATE' => 'true',
];

// Пустые переменные в Vercel считаем незаданными: иначе Laravel получает '' вместо значения
// по умолчанию (например, SESSION_DRIVER='' ломал все страницы).
foreach (getenv() as $key => $value) {
    if ($value === '') {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

/*
 * Хранилище медиа. Локальный диск на Vercel не работает (файлы не сохраняются между запросами),
 * поэтому, если подключён Vercel Blob, используем его, даже если в переменных стоит PUBLIC_DISK_DRIVER=local.
 * Токен ищем по значению: при подключении Blob переменная может получить свой префикс (не BLOB_).
 */
if (! getenv('BLOB_READ_WRITE_TOKEN')) {
    foreach (getenv() as $key => $value) {
        if (str_ends_with($key, 'READ_WRITE_TOKEN') && str_starts_with($value, 'vercel_blob_rw_')) {
            putenv("BLOB_READ_WRITE_TOKEN={$value}");
            $_ENV['BLOB_READ_WRITE_TOKEN'] = $_SERVER['BLOB_READ_WRITE_TOKEN'] = $value;
            break;
        }
    }
}

$disk = getenv('PUBLIC_DISK_DRIVER') ?: 'local';
if ($disk === 'local') {
    $disk = getenv('BLOB_READ_WRITE_TOKEN') ? 'blob' : (getenv('AWS_BUCKET') ? 's3' : 'local');
}
putenv("PUBLIC_DISK_DRIVER={$disk}");
$_ENV['PUBLIC_DISK_DRIVER'] = $_SERVER['PUBLIC_DISK_DRIVER'] = $disk;

/*
 * PHP на Vercel собран со старым libpq 13 без SNI, и Neon не может определить endpoint
 * ("Endpoint ID is not specified"). Обходной путь из документации Neon: передать endpoint
 * в пароле в виде "endpoint=<id>$<пароль>".
 */
foreach (['DB_URL', 'DATABASE_URL'] as $key) {
    $url = getenv($key);
    $parts = $url ? parse_url($url) : false;
    if (! $parts || ! str_ends_with($parts['host'] ?? '', '.neon.tech') || ! isset($parts['pass'])
        || str_starts_with(rawurldecode($parts['pass']), 'endpoint=')) {
        continue;
    }
    $endpoint = preg_replace('/-pooler$/', '', explode('.', $parts['host'])[0]);
    $password = rawurlencode('endpoint='.$endpoint.'$'.rawurldecode($parts['pass']));
    $url = preg_replace('#^([a-z]+://[^:/@]+:)[^@]*@#i', '${1}'.$password.'@', $url, 1);
    putenv("{$key}={$url}");
    $_ENV[$key] = $_SERVER[$key] = $url;
}

// Vercel принимает HTTPS и проксирует запрос во встроенный сервер PHP по HTTP.
// Без этого Laravel строит ссылки на CSS/JS с http://, и браузер блокирует их как mixed content.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

// Логи только в stderr: их видно во вкладке Logs на Vercel (файлы в /tmp никто не прочитает).
putenv('LOG_CHANNEL=stderr');
$_ENV['LOG_CHANNEL'] = $_SERVER['LOG_CHANNEL'] = 'stderr';

// Фатальные ошибки PHP тоже пишем в лог Vercel.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("PHP fatal: {$error['message']} at {$error['file']}:{$error['line']}");
    }
});

foreach (['/tmp/cache', '/tmp/storage/framework/views', '/tmp/storage/framework/cache', '/tmp/storage/framework/sessions', '/tmp/storage/logs', '/tmp/storage/app/public'] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

require __DIR__.'/../public/index.php';
