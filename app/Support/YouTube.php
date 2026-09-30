<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class YouTube
{
    public static function parseId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function thumbnail(string $id, string $quality = 'maxresdefault'): string
    {
        return "https://i.ytimg.com/vi/{$id}/{$quality}.jpg";
    }

    public static function embed(string $id, bool $autoplay = false): string
    {
        $params = ['rel' => 0, 'modestbranding' => 1, 'playsinline' => 1];
        if ($autoplay) {
            $params['autoplay'] = 1;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$id.'?'.http_build_query($params);
    }

    /** Фоновое видео для главной: без звука, по кругу, без элементов управления. */
    public static function background(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?'.http_build_query([
            'autoplay' => 1, 'mute' => 1, 'loop' => 1, 'playlist' => $id, 'controls' => 0,
            'showinfo' => 0, 'modestbranding' => 1, 'playsinline' => 1, 'rel' => 0, 'disablekb' => 1,
        ]);
    }

    /** ID канала из ссылки вида /channel/UC... или из настроек. */
    public static function channelId(?string $value): ?string
    {
        if ($value && preg_match('~(UC[A-Za-z0-9_-]{22})~', $value, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Последние видео канала. Сначала публичные RSS-ленты (канала и плейлиста загрузок),
     * а если YouTube их не отдаёт (бывает с облачных IP) — страница «Видео» канала.
     *
     * @return array<int, array{id: string, title: string, description: string, published: Carbon, views: int}>
     */
    public static function latest(string $channelId): array
    {
        $errors = [];
        $feeds = [
            ['channel_id' => $channelId],
            ['playlist_id' => 'UU'.substr($channelId, 2)],
        ];
        foreach ($feeds as $query) {
            $response = static::http()->get('https://www.youtube.com/feeds/videos.xml', $query);
            if ($response->successful() && ($videos = static::parseFeed($response->body()))) {
                return $videos;
            }
            $errors[] = 'RSS '.$response->status();
        }

        $response = static::http()->get("https://www.youtube.com/channel/{$channelId}/videos", ['hl' => 'en']);
        if ($response->successful() && ($videos = static::parseChannelPage($response->body()))) {
            return $videos;
        }
        $errors[] = 'page '.$response->status();

        throw new RuntimeException('YouTube: '.implode(', ', $errors));
    }

    protected static function http(): PendingRequest
    {
        return Http::timeout(10)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cookie' => 'SOCS=CAI; CONSENT=YES+1',
        ]);
    }

    public static function parseFeed(string $body): array
    {
        $xml = @simplexml_load_string($body);
        if (! $xml) {
            return [];
        }

        $videos = [];
        foreach ($xml->entry as $entry) {
            $yt = $entry->children('yt', true);
            $media = $entry->children('media', true)->group;
            $stats = $media?->children('media', true)->community?->statistics;
            $videos[] = [
                'id' => (string) $yt->videoId,
                'title' => trim((string) $entry->title),
                'description' => trim((string) ($media?->children('media', true)->description ?? '')),
                'published' => Carbon::parse((string) $entry->published),
                'views' => (int) ($stats ? $stats->attributes()['views'] : 0),
            ];
        }

        return $videos;
    }

    /** Разбирает ytInitialData со страницы «Видео» канала. */
    public static function parseChannelPage(string $html): array
    {
        if (! preg_match('/var ytInitialData = (\{.*?\});<\/script>/s', $html, $m)) {
            return [];
        }
        $data = json_decode($m[1], true);
        if (! is_array($data)) {
            return [];
        }

        $videos = [];
        $walk = function ($node) use (&$walk, &$videos) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['videoRenderer']['videoId'])) {
                $v = $node['videoRenderer'];
                $id = $v['videoId'];
                $videos[$id] ??= [
                    'id' => $id,
                    'title' => trim($v['title']['runs'][0]['text'] ?? $v['title']['simpleText'] ?? ''),
                    'description' => trim(implode('', array_column($v['descriptionSnippet']['runs'] ?? [], 'text'))),
                    'published' => static::relativeDate($v['publishedTimeText']['simpleText'] ?? ''),
                    'views' => (int) preg_replace('/\D/', '', $v['viewCountText']['simpleText'] ?? '0'),
                ];

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($data);

        return array_values(array_filter($videos, fn ($v) => $v['title'] !== ''));
    }

    /** «3 years ago» → примерная дата. */
    public static function relativeDate(string $text): Carbon
    {
        if (preg_match('/(\d+)\s+(second|minute|hour|day|week|month|year)/', $text, $m)) {
            return now()->sub($m[2], (int) $m[1]);
        }

        return now();
    }

    /** Добавляет в портфолио новые видео с канала. Возвращает число добавленных работ. */
    public static function import(string $channelId, bool $publish = true): int
    {
        $added = 0;
        foreach (static::latest($channelId) as $video) {
            if (Work::where('youtube_id', $video['id'])->exists()) {
                continue;
            }
            $title = $video['title'];
            $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', $video['description'])), 220);
            Work::create([
                'title' => ['hy' => $title, 'ru' => $title, 'en' => $title],
                'excerpt' => $excerpt ? ['hy' => $excerpt, 'ru' => $excerpt, 'en' => $excerpt] : null,
                'description' => $video['description'] ? ['hy' => $video['description'], 'ru' => $video['description'], 'en' => $video['description']] : null,
                'category' => static::guessCategory($title.' '.$video['description']),
                'video_url' => 'https://www.youtube.com/watch?v='.$video['id'],
                'year' => $video['published']->year,
                'views' => $video['views'],
                'is_published' => $publish,
                'published_at' => $video['published'],
            ]);
            $added++;
        }

        return $added;
    }

    /**
     * Живой сайт подтягивает свежие видео сам: не чаще раза в 6 часов,
     * после отправки ответа посетителю. Cron делает то же самое раз в сутки.
     */
    public static function syncIfStale(): void
    {
        if (! Setting::get('youtube_autosync', true)) {
            return;
        }
        $channel = static::channelId(Setting::get('youtube_channel_id'));
        $last = Setting::get('youtube_synced_at');
        // Пока с канала ничего не импортировано, пробуем чаще.
        $every = Work::whereNotNull('youtube_id')->exists() ? 360 : 10;
        if (! $channel || ($last && Carbon::parse($last)->gt(now()->subMinutes($every)))) {
            return;
        }
        Setting::put('youtube_synced_at', now()->toIso8601String());
        try {
            static::import($channel);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function guessCategory(string $text): string
    {
        $text = mb_strtolower($text);

        return match (true) {
            (bool) preg_match('/music video|official video|клип|տեսահոլովակ|\bmv\b|feat\.|ft\./u', $text) => 'music_video',
            (bool) preg_match('/short film|короткометраж|կարճամետրաժ/u', $text) => 'short',
            (bool) preg_match('/series|сериал|episode|серия|սերիա|սերիալ/u', $text) => 'series',
            (bool) preg_match('/documentary|документ|վավերագր/u', $text) => 'documentary',
            (bool) preg_match('/backstage|behind the scenes|bts|бэкстейдж|за кадром/u', $text) => 'backstage',
            (bool) preg_match('/commercial|реклам|գովազդ|promo|brand/u', $text) => 'commercial',
            default => 'film',
        };
    }
}
