<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Instagram;
use App\Support\YouTube;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Vercel Cron раз в день забирает новые видео с YouTube и посты из Instagram. */
class CronController extends Controller
{
    public function youtube(Request $request): JsonResponse
    {
        $secret = config('services.cron.secret');
        abort_unless($secret && hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        $result = [];
        foreach (['youtube' => fn () => static::youtubeNew(), 'instagram' => fn () => Instagram::syncNew()] as $key => $sync) {
            try {
                $result[$key] = $sync();
            } catch (Throwable $e) {
                report($e);
                $result[$key] = $e->getMessage();
            }
        }

        return response()->json($result);
    }

    protected static function youtubeNew(): int
    {
        $channel = YouTube::channelId(Setting::get('youtube_channel_id'));
        if (! $channel || ! Setting::get('youtube_autosync', true)) {
            return 0;
        }
        Setting::put('youtube_synced_at', now()->toIso8601String());

        return YouTube::import($channel);
    }
}
