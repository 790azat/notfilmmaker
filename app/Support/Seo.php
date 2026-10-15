<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Work;
use Illuminate\Support\Str;

class Seo
{
    public const OG_LOCALES = ['hy' => 'hy_AM', 'ru' => 'ru_RU', 'en' => 'en_US'];

    /** Индексируем только боевой сайт; превью-ветки (в т.ч. светлая тема) закрыты от поисковиков, чтобы не было дублей. */
    public static function indexable(): bool
    {
        return env('VERCEL_ENV', 'production') === 'production';
    }

    /** Основной адрес сайта без слеша на конце: APP_URL на проде, иначе текущий домен. */
    public static function base(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        return static::indexable() && $url !== '' && ! str_contains($url, 'localhost')
            ? $url
            : rtrim(request()->root(), '/');
    }

    /** Адрес страницы на нужном языке: /works?lang=ru. Без языка — адрес для x-default. */
    public static function localized(?string $locale, ?string $path = null, bool $absolute = true): string
    {
        $path = '/'.ltrim($path ?? request()->path(), '/');
        // Оставляем только значимые параметры (категорию работ), метки вроде utm отбрасываем.
        $query = collect(request()->query())->only(['c'])->filter(fn ($v) => is_string($v) && $v !== '');
        if ($locale) {
            $query->put('lang', $locale);
        }
        $url = ($absolute ? static::base() : '').($path === '/' ? '/' : $path);

        return $query->isEmpty() ? $url : $url.'?'.http_build_query($query->all());
    }

    public static function alternates(?string $path = null): array
    {
        return collect(array_keys(config('app.locales')))
            ->mapWithKeys(fn ($l) => [$l => static::localized($l, $path)])
            ->put('x-default', static::localized(null, $path))
            ->all();
    }

    public static function absolute(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    public static function sameAs(): array
    {
        return array_values(array_filter(
            [Setting::get('instagram'), Setting::get('youtube'), Setting::get('facebook')],
            fn ($u) => is_string($u) && Str::startsWith($u, ['http://', 'https://'])
        ));
    }

    public static function person(): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => static::base().'/#person',
            'name' => Setting::text('real_name') ?: Setting::text('name', 'notfilmmaker'),
            'alternateName' => Setting::text('name', 'notfilmmaker'),
            'jobTitle' => Setting::text('hero_title', 'Director · Cinematographer · Editor'),
            'description' => strip_tags(Setting::text('meta_description', __('site.meta_description'))),
            'image' => static::absolute(Media::url(Setting::get('portrait'))),
            'url' => static::base().'/',
            'email' => Setting::get('email') ? 'mailto:'.Setting::get('email') : null,
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => Setting::text('location', 'Yerevan, Armenia')],
            'sameAs' => static::sameAs() ?: null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => static::base().'/#website',
            'name' => Setting::text('name', 'notfilmmaker'),
            'url' => static::base().'/',
            'inLanguage' => array_keys(config('app.locales')),
            'publisher' => ['@id' => static::base().'/#person'],
        ];
    }

    public static function work(Work $work): array
    {
        $description = Str::limit(strip_tags($work->excerpt ?: $work->description ?: $work->title), 300);
        $date = ($work->published_at ?? $work->created_at)?->toAtomString();
        $data = [
            '@id' => static::localized(null, route('works.show', $work, false)).'#work',
            'name' => $work->title,
            'description' => $description,
            'url' => static::localized(app()->getLocale(), route('works.show', $work, false)),
            'inLanguage' => app()->getLocale(),
            'thumbnailUrl' => static::absolute($work->coverUrl()),
            'author' => ['@id' => static::base().'/#person'],
            'genre' => $work->categoryLabel(),
        ];

        if ($work->hasVideo()) {
            $data += [
                '@type' => 'VideoObject',
                'uploadDate' => $date,
                'embedUrl' => $work->embedUrl(),
                'contentUrl' => static::absolute($work->videoFileUrl()),
            ];
        } else {
            $data += ['@type' => 'CreativeWork', 'datePublished' => $date, 'image' => static::absolute($work->coverUrl())];
        }

        return array_filter($data);
    }

    /** Хлебные крошки: [[название, путь], ...]. */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => static::localized(app()->getLocale(), $item[1]),
            ])->all(),
        ];
    }

    /** Разметка schema.org одним графом. */
    public static function jsonLd(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );
    }
}
