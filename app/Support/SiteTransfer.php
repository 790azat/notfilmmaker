<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Переезд сайта на другой хостинг: копирование всех данных из старой базы
 * и перенос файлов из Vercel Blob на локальный диск.
 */
class SiteTransfer
{
    /** Таблицы в порядке вставки (сначала те, на которые ссылаются другие). */
    public const TABLES = ['users', 'settings', 'works', 'work_media', 'messages', 'chats', 'chat_messages', 'telegram_links'];

    private const BLOB_URL = '~https://[a-z0-9]+\.public\.blob\.vercel-storage\.com/([\w./-]+)~i';

    /** Все данные сайта из указанного подключения (по умолчанию из основной базы). */
    public static function export(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);
        $tables = [];
        foreach (static::TABLES as $table) {
            if ($schema->hasTable($table)) {
                $tables[$table] = $db->table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        return ['version' => 1, 'tables' => $tables];
    }

    /** Заменяет данные в таблицах на выгрузку. Возвращает число строк по таблицам. */
    public static function import(array $export): array
    {
        $counts = [];
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($export, &$counts) {
                foreach (array_reverse(static::TABLES) as $table) {
                    if (Schema::hasTable($table) && array_key_exists($table, $export['tables'])) {
                        DB::table($table)->delete();
                    }
                }
                foreach (static::TABLES as $table) {
                    $rows = $export['tables'][$table] ?? null;
                    if ($rows === null || ! Schema::hasTable($table)) {
                        continue;
                    }
                    $columns = Schema::getColumnListing($table);
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert(array_map(
                            fn ($row) => array_map(
                                fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v,
                                array_intersect_key($row, array_flip($columns)),
                            ),
                            $chunk,
                        ));
                    }
                    $counts[$table] = count($rows);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        static::resetSequences();

        return $counts;
    }

    /**
     * Скачивает все файлы из Vercel Blob, на которые ссылается база, на диск public
     * и заменяет ссылки на локальные пути. Повторный запуск докачивает то, что не скачалось.
     */
    public static function localizeMedia(?callable $progress = null): array
    {
        $disk = Storage::disk('public');
        $stats = ['files' => 0, 'failed' => 0, 'rows' => 0];
        $cache = [];

        $localize = function (string $url) use ($disk, &$stats, &$cache, $progress): ?string {
            if (array_key_exists($url, $cache)) {
                return $cache[$url];
            }
            preg_match(static::BLOB_URL, $url, $m);
            $path = ltrim($m[1], '/');
            if (! $disk->exists($path)) {
                $response = Http::timeout(300)->retry(2, 1000, throw: false)->get($url);
                if (! $response->successful()) {
                    $stats['failed']++;
                    $progress && $progress("FAIL {$url}: HTTP {$response->status()}");

                    return $cache[$url] = null;
                }
                $disk->put($path, $response->body());
                $stats['files']++;
                $progress && $progress($path);
            }

            return $cache[$url] = $path;
        };

        $walk = function ($value) use (&$walk, $localize) {
            if (is_array($value)) {
                return array_map($walk, $value);
            }
            if (is_string($value) && preg_match('~^'.substr(static::BLOB_URL, 1, -2).'$~i', $value)) {
                return $localize($value) ?? $value;
            }

            return $value;
        };

        foreach (static::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $key = $table === 'settings' ? 'key' : 'id';
            foreach (DB::table($table)->get() as $row) {
                $changes = [];
                foreach ((array) $row as $column => $value) {
                    if (! is_string($value) || ! str_contains($value, '.blob.vercel-storage.com')) {
                        continue;
                    }
                    $json = json_decode($value, true);
                    $new = json_last_error() === JSON_ERROR_NONE && (is_array($json) || is_string($json))
                        ? json_encode($walk($json), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : $walk($value);
                    if ($new !== $value) {
                        $changes[$column] = $new;
                    }
                }
                if ($changes) {
                    DB::table($table)->where($key, $row->{$key})->update($changes);
                    $stats['rows']++;
                }
            }
        }

        return $stats;
    }

    /** После вставки строк с явными id в Postgres счётчики нужно сдвинуть вручную (MySQL делает это сам). */
    protected static function resetSequences(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach (static::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id') && $table !== 'settings') {
                DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), coalesce((select max(id) from {$table}), 0) + 1, false)");
            }
        }
    }
}
