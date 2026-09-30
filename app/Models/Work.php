<?php

namespace App\Models;

use App\Support\Media;
use App\Support\YouTube;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Work extends Model
{
    use HasTranslations;

    public const CATEGORIES = ['film', 'short', 'series', 'music_video', 'commercial', 'documentary', 'photo', 'reels', 'backstage'];

    public array $translatable = ['title', 'excerpt', 'description', 'role'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Work $work) {
            if (! $work->slug) {
                $base = Str::slug($work->getTranslation('title', 'en', false) ?: $work->getTranslation('title', 'ru', false) ?: $work->getTranslation('title', 'hy', false)) ?: 'work';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $work->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $work->slug = $slug;
            }

            $work->youtube_id = YouTube::parseId($work->video_url);
        });

        static::deleting(function (Work $work) {
            Media::delete($work->cover);
            $work->media->each(fn (WorkMedia $m) => Media::delete($m->path));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function coverUrl(): ?string
    {
        if ($this->cover) {
            return Media::url($this->cover);
        }
        if ($this->youtube_id) {
            return YouTube::thumbnail($this->youtube_id);
        }
        $first = $this->media->firstWhere('type', 'image');

        return $first ? Media::url($first->path) : null;
    }

    public function embedUrl(bool $autoplay = false): ?string
    {
        if ($this->youtube_id) {
            return YouTube::embed($this->youtube_id, $autoplay);
        }
        if ($this->video_url && preg_match('~instagram\.com/(?:[\w.]+/)?(p|reel|tv)/([\w-]+)~', $this->video_url, $m)) {
            return 'https://www.instagram.com/'.$m[1].'/'.$m[2].'/embed';
        }
        if ($this->video_url && preg_match('~vimeo\.com/(?:video/)?(\d+)~', $this->video_url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1].($autoplay ? '?autoplay=1' : '');
        }

        return null;
    }

    /** Прямой файл видео (загруженный в хранилище или ссылка на .mp4). */
    public function videoFileUrl(): ?string
    {
        if (! $this->video_url || $this->embedUrl()) {
            return null;
        }

        return Media::url($this->video_url);
    }

    public function hasVideo(): bool
    {
        return (bool) ($this->embedUrl() || $this->videoFileUrl());
    }

    public function categoryLabel(): string
    {
        return __('site.categories.'.$this->category);
    }
}
