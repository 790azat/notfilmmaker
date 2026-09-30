<?php

namespace App\Livewire\Works;

use App\Models\Work;
use App\Support\Seo;
use Illuminate\Support\Str;
use Livewire\Component;

class Show extends Component
{
    public Work $work;

    public function mount(Work $work): void
    {
        abort_unless($work->is_published || auth()->user()?->is_admin, 404);
        $this->work = $work->load('media');
    }

    public function render()
    {
        $related = Work::published()->with('media')
            ->where('id', '!=', $this->work->id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$this->work->category])
            ->ordered()->take(3)->get();

        $index = Work::published()->ordered()->pluck('id')->values();
        $pos = $index->search($this->work->id);
        $next = $pos !== false ? Work::find($index[($pos + 1) % max(1, $index->count())] ?? null) : null;

        return view('livewire.works.show', compact('related', 'next'))
            ->layout('layouts.site', [
                'title' => $this->work->title,
                'description' => Str::limit(strip_tags($this->work->excerpt ?: $this->work->description ?: ''), 160) ?: null,
                'ogImage' => $this->work->coverUrl(),
                'ogType' => $this->work->hasVideo() ? 'video.other' : 'article',
                'schema' => [Seo::work($this->work), Seo::breadcrumbs([
                    [__('site.nav.home'), '/'],
                    [__('site.works.title'), route('works.index', absolute: false)],
                    [$this->work->title, route('works.show', $this->work, false)],
                ])],
            ]);
    }
}
