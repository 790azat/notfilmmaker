<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Setting;
use App\Models\Work;
use App\Support\Media;
use App\Support\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Альтернативная версия сайта: 3D-рабочий стол режиссёра (/alternative). */
class AlternativeController extends Controller
{
    public const ALBUMS = 8;

    public const PHOTOS = 6;

    public function show(): View
    {
        $works = Work::published()->with('media')->ordered()->get();

        // Пластинки на полке: сначала клипы, потом остальные работы с видео-файлом или плеером.
        $albums = $works->filter(fn (Work $w) => $w->hasVideo())
            ->sortBy(fn (Work $w) => [$w->category === 'music_video' ? 0 : 1, $w->is_featured ? 0 : 1])
            ->take(self::ALBUMS)
            ->map(fn (Work $w) => $this->album($w))
            ->values();

        $photos = $works->filter(fn (Work $w) => $w->coverUrl())
            ->sortBy(fn (Work $w) => $w->category === 'photo' ? 0 : 1)
            ->reject(fn (Work $w) => $albums->contains('id', $w->id) && $works->count() > self::ALBUMS + self::PHOTOS)
            ->take(self::PHOTOS)
            ->map(fn (Work $w) => ['id' => $w->id, 'title' => $w->title, 'cover' => $w->coverUrl(), 'url' => route('works.show', $w)])
            ->values();

        $site = [
            'name' => Setting::text('name', 'notfilmmaker'),
            'realName' => Setting::text('real_name', ''),
            'role' => Setting::text('hero_title', 'Director · Cinematographer · Editor'),
            'about' => Str::limit(strip_tags((string) Setting::text('about_text', '')), 900),
            'portrait' => Media::url(Setting::get('portrait')),
            'email' => Setting::get('email'),
            'phone' => Setting::get('phone'),
            'location' => Setting::text('location', 'Yerevan, Armenia'),
            'socials' => array_filter([
                'Instagram' => Setting::get('instagram'),
                'YouTube' => Setting::get('youtube'),
                'Facebook' => Setting::get('facebook'),
            ]),
            'worksCount' => $works->count(),
            'urls' => [
                'home' => route('home'),
                'works' => route('works.index'),
                'about' => route('about'),
                'contact' => route('contact'),
                'book' => route('alternative.book'),
            ],
        ];

        return view('alternative', [
            'data' => ['albums' => $albums, 'photos' => $photos, 'site' => $site, 'i18n' => trans('alternative'), 'locale' => app()->getLocale()],
            'site' => $site,
        ]);
    }

    /** Заявка с календаря на стене: съёмка, встреча в студии или созвон. */
    public function book(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'kind' => ['required', 'in:shoot,visit,call'],
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string'],
        ]);

        // Ловушка для ботов: поле скрыто от людей.
        if (! empty($data['website'])) {
            return response()->json(['ok' => true]);
        }

        $key = 'book:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'message' => __('alternative.book.throttle')], 429);
        }
        RateLimiter::hit($key, 3600);

        $isEmail = filter_var($data['contact'], FILTER_VALIDATE_EMAIL) !== false;
        $kind = __('alternative.book.kinds.'.$data['kind'], [], 'ru');
        $date = $data['date'];

        $message = Message::create([
            'name' => $data['name'],
            'email' => $isEmail ? $data['contact'] : '',
            'phone' => $isEmail ? null : $data['contact'],
            'project_type' => "📅 {$kind} · {$date}",
            'body' => trim("Запись через 3D-стол (/alternative): {$kind}, {$date}.\n\n".($data['note'] ?? '')),
            'locale' => app()->getLocale(),
        ]);

        Telegram::notify("📅 Запись с 3D-стола\n{$kind}: {$date}\n{$message->name}, {$data['contact']}".(! empty($data['note']) ? "\n\n{$data['note']}" : ''), 'mail:'.$message->id);

        return response()->json(['ok' => true]);
    }

    private function album(Work $w): array
    {
        return [
            'id' => $w->id,
            'title' => $w->title,
            'category' => $w->categoryLabel(),
            'year' => $w->year,
            'cover' => $w->coverUrl(),
            'video' => $w->videoFileUrl(),
            'embed' => $w->embedUrl(true),
            'url' => route('works.show', $w),
        ];
    }
}
