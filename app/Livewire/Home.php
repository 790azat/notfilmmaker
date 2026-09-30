<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\Work;
use App\Support\YouTube;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        $featured = Work::published()->with('media')->where('is_featured', true)->ordered()->take(5)->get();
        if ($featured->count() < 3) {
            $featured = $featured->merge(
                Work::published()->with('media')->whereNotIn('id', $featured->pluck('id'))->ordered()->take(5 - $featured->count())->get()
            );
        }

        $latestVideos = Work::published()->whereNotNull('youtube_id')->orderByDesc('published_at')->orderByDesc('id')->take(8)->get();

        $showreel = YouTube::parseId(Setting::get('showreel_url'))
            ?? $featured->firstWhere('youtube_id')?->youtube_id
            ?? $latestVideos->first()?->youtube_id;

        $stats = [
            'projects' => max((int) Setting::get('stat_projects', 0), Work::published()->count()),
            'years' => (int) Setting::get('stat_years', 0),
            'views' => max((int) Setting::get('stat_views', 0), (int) Work::sum('views')),
        ];

        return view('livewire.home', compact('featured', 'latestVideos', 'showreel', 'stats'))
            ->layout('layouts.site');
    }
}
