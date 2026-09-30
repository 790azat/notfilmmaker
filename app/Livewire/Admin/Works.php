<?php

namespace App\Livewire\Admin;

use App\Models\Work;
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
