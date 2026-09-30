<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Видео в шапке главной: выбранные в админке по порядку, остальное добирается автоматически. */
class HeroReels
{
    public const SLOTS = 5;

    /** Загруженные видео (не YouTube и не ссылки на Instagram). */
    public static function query(): Builder
    {
        return Work::published()->whereNotNull('video_url')->whereNull('youtube_id')
            ->where('video_url', 'not like', '%instagram.com%');
    }

    /** @return list<int> */
    public static function chosenIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) Setting::get('hero_reels', [])))));
    }

    public static function pick(Collection $fallback): Collection
    {
        $ids = self::chosenIds();
        $chosen = $ids
            ? self::query()->with('media')->whereIn('id', $ids)->get()->sortBy(fn ($w) => array_search($w->id, $ids))->values()
            : collect();

        return $chosen->concat($fallback->whereNotIn('id', $chosen->pluck('id')))->take(self::SLOTS)->values();
    }
}
