<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\YouTube;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vercel Cron раз в день забирает новые видео с YouTube-канала. */
class CronController extends Controller
{
    public function youtube(Request $request): JsonResponse
    {
        $secret = config('services.cron.secret');
        abort_unless($secret && hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        $channel = YouTube::channelId(Setting::get('youtube_channel_id'));
        abort_unless($channel && Setting::get('youtube_autosync', true), 204);

        return response()->json(['added' => YouTube::import($channel)]);
    }
}
