<?php

namespace App\Livewire\Works;

use App\Models\Work;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'c', except: '')]
    public string $category = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public int $perPage = 12;

    public function setCategory(string $category): void
    {
        $this->category = in_array($category, Work::CATEGORIES, true) ? $category : '';
        $this->perPage = 12;
    }

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    public function updatedSearch(): void
    {
        $this->perPage = 12;
    }

    public function render()
    {
        $works = Work::published()->with('media')
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->ordered()->get();

        // Названия хранятся в JSON с экранированием (\uXXXX), поэтому ищем по переводам в PHP.
        $term = mb_strtolower(trim($this->search));
        if ($term !== '') {
            $works = $works->filter(function (Work $work) use ($term) {
                $haystack = collect(['title', 'excerpt', 'description', 'role'])
                    ->flatMap(fn ($field) => array_values($work->getTranslations($field)))
                    ->push($work->client, $work->year)
                    ->implode(' ');

                return str_contains(mb_strtolower($haystack), $term);
            });
        }

        $total = $works->count();
        $works = $works->take($this->perPage);

        $counts = Work::published()->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');

        return view('livewire.works.index', [
            'works' => $works,
            'total' => $total,
            'categories' => collect(Work::CATEGORIES)->filter(fn ($c) => ($counts[$c] ?? 0) > 0),
            'counts' => $counts,
        ])->layout('layouts.site', ['title' => __('site.works.title')]);
    }
}
