<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Импорт постов из Instagram через официальный Instagram API (Instagram Login).
 * Файлы копируются в своё хранилище: ссылки Instagram на картинки живут недолго.
 */
class Instagram
{
    protected const API = 'https://graph.instagram.com/v22.0';

    protected const FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{media_type,media_url,thumbnail_url}';

    /** Самые большие видео не копируем, а показываем плеером Instagram. */
    protected const MAX_VIDEO_BYTES = 80 * 1024 * 1024;

    public static function token(): ?string
    {
        return Setting::get('instagram_token') ?: null;
    }

    /** Имя аккаунта, к которому относится токен (заодно проверка токена). */
    public static function account(string $token): string
    {
        $response = Http::timeout(15)->get(self::API.'/me', ['fields' => 'username', 'access_token' => $token]);
        if (! $response->successful()) {
            throw new RuntimeException(static::error($response->json(), $response->status()));
        }

        return (string) $response->json('username');
    }

    /** Долгоживущий токен работает 60 дней; продлеваем его при каждой синхронизации. */
    public static function refreshToken(): void
    {
        $token = static::token();
        if (! $token) {
            return;
        }
        $response = Http::timeout(15)->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);
        if ($response->successful() && $response->json('access_token')) {
            Setting::put('instagram_token', $response->json('access_token'));
        }
    }

    /**
     * Импортирует посты порциями, чтобы уложиться во время одного запроса.
     * Курсор хранится в настройках: следующий вызов продолжит с того же места.
     *
     * @return array{added: int, more: bool}
     */
    public static function importBatch(int $seconds = 20, bool $fromStart = false): array
    {
        $token = static::token() ?? throw new RuntimeException(__('admin.instagram.no_token'));
        @ini_set('memory_limit', '1024M');
        $started = microtime(true);
        $cursor = $fromStart ? null : Setting::get('instagram_cursor');
        $added = 0;

        while (true) {
            $response = Http::timeout(20)->get(self::API.'/me/media', array_filter([
                'fields' => self::FIELDS,
                'limit' => 25,
                'after' => $cursor,
                'access_token' => $token,
            ]));
            if (! $response->successful()) {
                throw new RuntimeException(static::error($response->json(), $response->status()));
            }

            foreach ($response->json('data', []) as $post) {
                if (Work::where('instagram_id', $post['id'])->exists()) {
                    continue;
                }
                if (microtime(true) - $started > $seconds) {
                    Setting::put('instagram_cursor', $cursor);

                    return ['added' => $added, 'more' => true];
                }
                static::importPost($post);
                $added++;
            }

            $cursor = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
            Setting::put('instagram_cursor', $cursor);
            if (! $cursor) {
                return ['added' => $added, 'more' => false];
            }
        }
    }

    /** Новые посты для ежедневной синхронизации: первая страница ленты. */
    public static function syncNew(): int
    {
        if (! static::token() || ! Setting::get('instagram_autosync', true)) {
            return 0;
        }
        static::refreshToken();
        $cursor = Setting::get('instagram_cursor');
        try {
            return static::importBatch(20, fromStart: true)['added'];
        } finally {
            // Не сбиваем незаконченный полный импорт.
            if ($cursor) {
                Setting::put('instagram_cursor', $cursor);
            }
        }
    }

    public static function importPost(array $post): Work
    {
        $caption = trim((string) ($post['caption'] ?? ''));
        $published = Carbon::parse($post['timestamp'] ?? now());
        $type = $post['media_type'] ?? 'IMAGE';

        $items = $type === 'CAROUSEL_ALBUM'
            ? ($post['children']['data'] ?? [])
            : [$post];

        $cover = null;
        $video = null;
        $gallery = [];
        foreach ($items as $i => $item) {
            $isVideo = ($item['media_type'] ?? '') === 'VIDEO';
            if ($isVideo) {
                $thumb = static::copy($item['thumbnail_url'] ?? null, 'covers');
                $cover ??= $thumb;
                $file = static::copy($item['media_url'] ?? null, 'videos', self::MAX_VIDEO_BYTES);
                if ($type === 'VIDEO') {
                    $video = $file ?? ($post['permalink'] ?? null);
                } elseif ($file) {
                    $gallery[] = ['type' => 'video', 'path' => $file];
                }
            } else {
                $image = static::copy($item['media_url'] ?? null, $type === 'CAROUSEL_ALBUM' ? 'gallery' : 'covers');
                if (! $image) {
                    continue;
                }
                if ($type === 'CAROUSEL_ALBUM') {
                    $gallery[] = ['type' => 'image', 'path' => $image];
                    $cover ??= $image;
                } else {
                    $cover = $image;
                }
            }
        }

        $title = static::title($caption, $published);
        $excerpt = $caption ? Str::limit(preg_replace('/\s+/', ' ', $caption), 220) : null;

        $work = Work::create([
            'title' => ['hy' => $title, 'ru' => $title, 'en' => $title],
            'excerpt' => $excerpt ? ['hy' => $excerpt, 'ru' => $excerpt, 'en' => $excerpt] : null,
            'description' => $caption ? ['hy' => $caption, 'ru' => $caption, 'en' => $caption] : null,
            'category' => $type === 'VIDEO' ? YouTube::guessCategory($caption) : 'photo',
            'cover' => $type === 'CAROUSEL_ALBUM' ? null : $cover,
            'video_url' => $video,
            'instagram_id' => $post['id'],
            'year' => $published->year,
            'is_published' => true,
            'published_at' => $published,
        ]);

        foreach ($gallery as $i => $media) {
            $work->media()->create($media + ['sort_order' => $i]);
        }

        return $work;
    }

    /** Первая строка подписи без хэштегов, иначе дата. */
    public static function title(string $caption, Carbon $date): string
    {
        $line = trim(preg_replace('/[#@][\p{L}\p{N}_.]+/u', '', strtok($caption, "\n") ?: ''));
        $line = trim($line, " \t-–—|•.,:");

        return $line !== '' ? Str::limit($line, 80) : 'Instagram · '.$date->format('d.m.Y');
    }

    /** Скачивает файл Instagram и кладёт в своё хранилище. */
    protected static function copy(?string $url, string $folder, ?int $maxBytes = null): ?string
    {
        if (! $url) {
            return null;
        }
        try {
            $response = Http::timeout(25)->get($url);
            if (! $response->successful() || ($maxBytes && strlen($response->body()) > $maxBytes)) {
                return null;
            }
            $mime = strtok($response->header('Content-Type') ?: 'image/jpeg', ';');
            $ext = match ($mime) {
                'video/mp4' => 'mp4',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/heic' => 'heic',
                default => str_starts_with($mime, 'video/') ? 'mp4' : 'jpg',
            };

            return Media::store($folder, $response->body(), $ext, $mime);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected static function error(?array $json, int $status): string
    {
        return 'Instagram: '.($json['error']['message'] ?? 'HTTP '.$status);
    }
}
