<?php

namespace App\Livewire;

use App\Models\Work;
use Livewire\Component;

class About extends Component
{
    public function render()
    {
        $photos = Work::published()->with('media')->ordered()->take(6)->get();

        return view('livewire.about', compact('photos'))
            ->layout('layouts.site', ['title' => __('site.about.title')]);
    }
}
