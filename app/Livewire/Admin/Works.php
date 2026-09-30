<?php

namespace App\Livewire\Admin;

use App\Models\Work;
use App\Support\Instagram;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class Works extends Component
{
    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $search = '';

    public function toggle(int $id, string $field): void
    {
        abort_unless(in_array($field, ['is_published', 'is_featured'], true), 400);
        $work = Work::findOrFail($id);
        $work->update([$field => ! $work->{$field}]);
    }

    public function move(int $id, int $direction): void
    {
        $ids = Work::ordered()->pluck('id')->values();
        $pos = $ids->search($id);
        $swap = $pos + $direction;
        if ($pos === false || $swap < 0 || $swap >= $ids->count()) {
            return;
        }
        $list = $ids->all();
        [$list[$pos], $list[$swap]] = [$list[$swap], $list[$pos]];
        foreach ($list as $i => $workId) {
            Work::whereKey($workId)->update(['sort_order' => $i]);
        }
    }

    public function delete(int $id): void
    {
        Work::findOrFail($id)->delete();
        $this->dispatch('toast', text: __('admin.works.deleted'));
    }

    /** Коды постов Instagram, которые уже есть на сайте (архив их пропускает). */
    public function instagramCodes(): array
    {
        return Work::whereNotNull('instagram_id')->pluck('instagram_id')->all();
    }

    /** Пост из архива Instagram: файлы уже загружены браузером, здесь создаётся работа. */
    public function importArchivePost(string $code, string $kind, array $items, ?string $cover, int $timestamp): bool
    {
        abort_unless(preg_match('/^[\w-]{5,40}$/', $code) && in_array($kind, ['video', 'photo', 'carousel'], true), 422);
        if (Work::where('instagram_id', $code)->exists()) {
            return false;
        }

        $valid = fn (?string $path) => $path && (
            preg_match('~^https://[a-z0-9]+\.public\.blob\.vercel-storage\.com/(covers|gallery|videos)/[\w.-]+$~i', $path)
            || preg_match('~^(covers|gallery|videos)/[\w.-]+$~', $path)
        );
        $items = collect($items)
            ->map(fn ($i) => ['type' => ($i['type'] ?? '') === 'video' ? 'video' : 'image', 'path' => (string) ($i['path'] ?? '')])
            ->filter(fn ($i) => $valid($i['path']))
            ->values();
        abort_if($items->isEmpty() || ($cover && ! $valid($cover)), 422);

        $published = Carbon::createFromTimestampMs($timestamp);
        if ($published->year < 2010 || $published->isFuture()) {
            $published = now();
        }

        $first = $items->first();
        Instagram::createWork(
            code: $code,
            kind: $kind,
            cover: $kind === 'photo' ? $first['path'] : $cover,
            video: $kind === 'video' ? $items->firstWhere('type', 'video')['path'] ?? null : null,
            gallery: $kind === 'carousel' ? $items->all() : [],
            published: $published,
        );

        return true;
    }

    public function render()
    {
        $works = Work::with('media')
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->ordered()->get();

        $term = mb_strtolower(trim($this->search));
        if ($term !== '') {
            $works = $works->filter(fn (Work $w) => str_contains(mb_strtolower(implode(' ', $w->getTranslations('title'))), $term));
        }

        return view('livewire.admin.works', ['works' => $works])
            ->layout('layouts.admin', ['title' => __('admin.nav.works')]);
    }
}
