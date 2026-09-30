<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Разовый импорт архива постов Instagram (database/data/instagram-archive.json).
 * Файлы лежат в ветке instagram-archive репозитория; сайт скачивает их порциями,
 * кладёт в своё хранилище и создаёт работы. Уже импортированные посты пропускаются.
 */
class InstagramArchive
{
    public static function manifest(): array
    {
        $file = database_path('data/instagram-archive.json');

        return is_file($file) ? json_decode(file_get_contents($file), true) : [];
    }

    public static function version(): string
    {
        $file = database_path('data/instagram-archive.json');

        return is_file($file) ? md5_file($file) : '';
    }

    public static function pending(): bool
    {
        return static::version() !== '' && Setting::get('instagram_archive_done') !== static::version();
    }

    /** @return array{added: int, left: int, busy?: bool} */
    public static function run(int $seconds = 40): array
    {
        if (! static::pending()) {
            return ['added' => 0, 'left' => 0];
        }
        $lock = Setting::get('instagram_archive_lock');
        if ($lock && Carbon::parse($lock)->isFuture()) {
            return ['added' => 0, 'left' => -1, 'busy' => true];
        }
        Setting::put('instagram_archive_lock', now()->addSeconds($seconds + 30)->toIso8601String());
        @ini_set('memory_limit', '1024M');

        $started = microtime(true);
        $manifest = static::manifest();
        $base = $manifest['base'];
        $added = 0;

        try {
            foreach ($manifest['site'] ?? [] as $key => $path) {
                if (! Setting::get($key)) {
                    Setting::put($key, static::fetch($base, $path, 'site'));
                }
            }

            $existing = Work::whereNotNull('instagram_id')->pluck('instagram_id')->flip();
            $todo = array_values(array_filter($manifest['posts'], fn ($p) => ! isset($existing[$p['code']])));

            foreach ($todo as $i => $post) {
                if (microtime(true) - $started > $seconds) {
                    return ['added' => $added, 'left' => count($todo) - $i];
                }
                static::import($base, $post, in_array($post['code'], $manifest['featured'] ?? [], true));
                $added++;
            }

            Setting::put('instagram_archive_done', static::version());

            return ['added' => $added, 'left' => 0];
        } finally {
            Setting::put('instagram_archive_lock', null);
        }
    }

    protected static function import(string $base, array $post, bool $featured): void
    {
        $gallery = [];
        $video = null;
        foreach ($post['files'] as $file) {
            if ($file['type'] === 'video') {
                $url = static::fetch($base, $file['path'], 'videos');
                $post['kind'] === 'video' ? $video = $url : $gallery[] = ['type' => 'video', 'path' => $url];
            } else {
                $gallery[] = ['type' => 'image', 'path' => static::fetch($base, $file['path'], $post['kind'] === 'photo' ? 'covers' : 'gallery')];
            }
        }
        foreach ($post['stills'] ?? [] as $still) {
            $gallery[] = ['type' => 'image', 'path' => static::fetch($base, $still, 'gallery')];
        }

        $cover = null;
        if ($post['kind'] === 'photo') {
            $cover = array_shift($gallery)['path'];
        } elseif ($post['poster'] ?? null) {
            $cover = static::fetch($base, $post['poster'], 'covers');
        }

        $work = Instagram::createWork(
            code: $post['code'],
            kind: $post['kind'],
            cover: $cover,
            video: $video,
            gallery: $gallery,
            published: Carbon::createFromTimestampMs($post['time']),
        );
        if ($featured) {
            $work->update(['is_featured' => true]);
        }
    }

    protected static function fetch(string $base, string $path, string $folder): string
    {
        $response = Http::timeout(60)->retry(2, 500)->get($base.$path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'mp4' ? 'video/mp4' : 'image/jpeg';

        return Media::store($folder, $response->body(), $ext, $mime);
    }
}
