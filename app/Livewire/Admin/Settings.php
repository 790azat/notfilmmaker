<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Instagram;
use App\Support\Media;
use App\Support\YouTube;
use Livewire\Component;
use Throwable;

class Settings extends Component
{
    public const TRANSLATABLE = ['name', 'hero_name', 'real_name', 'hero_kicker', 'hero_title', 'about_title', 'about_text', 'location', 'meta_description'];

    public const PLAIN = ['email', 'phone', 'instagram', 'facebook', 'youtube', 'youtube_channel_id', 'showreel_url', 'stat_years', 'stat_projects', 'stat_views'];

    public const FLAGS = ['registration_open', 'youtube_autosync', 'instagram_autosync'];

    public const IMAGES = ['portrait', 'hero_image', 'og_image'];

    public array $t = [];

    public array $p = [];

    public array $f = [];

    public array $images = [];

    public string $igToken = '';

    public ?string $igAccount = null;

    public function mount(): void
    {
        foreach (self::TRANSLATABLE as $key) {
            $value = Setting::get($key);
            $this->t[$key] = array_merge(['hy' => '', 'ru' => '', 'en' => ''], is_array($value) ? $value : ['hy' => (string) $value, 'ru' => (string) $value, 'en' => (string) $value]);
        }
        foreach (self::PLAIN as $key) {
            $this->p[$key] = (string) Setting::get($key, '');
        }
        $this->f['registration_open'] = (bool) Setting::get('registration_open', false);
        $this->f['youtube_autosync'] = (bool) Setting::get('youtube_autosync', true);
        $this->f['instagram_autosync'] = (bool) Setting::get('instagram_autosync', true);
        $this->igAccount = Instagram::token() ? Setting::get('instagram_username', '✓') : null;
        foreach (self::IMAGES as $key) {
            $this->images[$key] = Setting::get($key);
        }
    }

    public function setPortrait(string $path): void
    {
        $this->replaceImage('portrait', $path);
    }

    public function setHeroImage(string $path): void
    {
        $this->replaceImage('hero_image', $path);
    }

    public function setOgImage(string $path): void
    {
        $this->replaceImage('og_image', $path);
    }

    public function removeImage(string $key): void
    {
        abort_unless(in_array($key, self::IMAGES, true), 400);
        $this->replaceImage($key, null);
    }

    private function replaceImage(string $key, ?string $path): void
    {
        Media::delete($this->images[$key] ?? null);
        $this->images[$key] = $path;
        Setting::put($key, $path);
        $this->dispatch('toast', text: __('admin.saved'));
    }

    public function save(): void
    {
        $this->validate([
            't.*.*' => ['nullable', 'string', 'max:10000'],
            'p.email' => ['nullable', 'email'],
            'p.instagram' => ['nullable', 'url'],
            'p.facebook' => ['nullable', 'url'],
            'p.youtube' => ['nullable', 'url'],
            'p.showreel_url' => ['nullable', 'string', 'max:300'],
            'p.stat_years' => ['nullable', 'integer', 'min:0'],
            'p.stat_projects' => ['nullable', 'integer', 'min:0'],
            'p.stat_views' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach (self::TRANSLATABLE as $key) {
            $values = array_map('trim', $this->t[$key]);
            Setting::put($key, array_filter($values) ? $values : null);
        }
        foreach (self::PLAIN as $key) {
            Setting::put($key, trim($this->p[$key]) ?: null);
        }
        foreach (self::FLAGS as $key) {
            Setting::put($key, (bool) ($this->f[$key] ?? false));
        }

        $this->dispatch('toast', text: __('admin.saved'));
    }

    public function syncYouTube(): void
    {
        $this->save();
        $channel = YouTube::channelId($this->p['youtube_channel_id'] ?: $this->p['youtube']);
        if (! $channel) {
            $this->dispatch('toast', text: __('admin.youtube.no_channel'), type: 'error');

            return;
        }
        try {
            $this->dispatch('toast', text: __('admin.youtube.synced', ['n' => YouTube::import($channel)]));
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', text: __('admin.youtube.failed').' '.$e->getMessage(), type: 'error');
        }
    }

    public function connectInstagram(): void
    {
        $token = trim($this->igToken);
        $this->validate(['igToken' => ['required', 'string', 'min:20']]);
        try {
            $username = Instagram::account($token);
        } catch (Throwable $e) {
            $this->addError('igToken', $e->getMessage());

            return;
        }
        Setting::put('instagram_token', $token);
        Setting::put('instagram_username', $username);
        Setting::put('instagram_cursor', null);
        $this->igToken = '';
        $this->igAccount = $username;
        $this->dispatch('toast', text: __('admin.instagram.connected', ['name' => $username]));
    }

    public function disconnectInstagram(): void
    {
        foreach (['instagram_token', 'instagram_username', 'instagram_cursor'] as $key) {
            Setting::put($key, null);
        }
        $this->igAccount = null;
    }

    /** Одна порция импорта; кнопка в админке вызывает её, пока есть что забирать. */
    public function importInstagram(bool $fromStart = false): array
    {
        try {
            return Instagram::importBatch(20, $fromStart);
        } catch (Throwable $e) {
            report($e);

            return ['added' => 0, 'more' => false, 'error' => $e->getMessage()];
        }
    }

    public function render()
    {
        return view('livewire.admin.settings')->layout('layouts.admin', ['title' => __('admin.nav.settings')]);
    }
}
