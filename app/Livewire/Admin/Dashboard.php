<?php

namespace App\Livewire\Admin;

use App\Models\Message;
use App\Models\Setting;
use App\Models\Work;
use App\Support\YouTube;
use Livewire\Component;
use Throwable;

class Dashboard extends Component
{
    public function syncYouTube(): void
    {
        $channel = YouTube::channelId(Setting::get('youtube_channel_id'));
        if (! $channel) {
            $this->dispatch('toast', text: __('admin.youtube.no_channel'), type: 'error');

            return;
        }
        try {
            $added = YouTube::import($channel);
            $this->dispatch('toast', text: __('admin.youtube.synced', ['n' => $added]));
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', text: __('admin.youtube.failed').' '.$e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.admin.dashboard', [
            'stats' => [
                ['film', __('admin.dashboard.published'), Work::where('is_published', true)->count()],
                ['eye-off', __('admin.dashboard.drafts'), Work::where('is_published', false)->count()],
                ['inbox', __('admin.dashboard.unread'), Message::whereNull('read_at')->count()],
                ['eye', __('admin.dashboard.views'), number_format((int) Work::sum('views'), 0, '.', ' ')],
            ],
            'messages' => Message::latest()->take(5)->get(),
            'recent' => Work::with('media')->latest('updated_at')->take(6)->get(),
        ])->layout('layouts.admin', ['title' => __('admin.nav.dashboard')]);
    }
}
