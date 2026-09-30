@php use App\Support\Media; @endphp
<form wire:submit="save" class="space-y-6" x-data="{ lang: '{{ app()->getLocale() }}' }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="display text-4xl">{{ __('admin.nav.settings') }}</h1>
        <div class="flex items-center gap-3">
            @include('partials.admin.lang-tabs')
            <button type="submit" class="btn-primary !py-2.5" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> {{ __('admin.save') }}</button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card space-y-5">
            <h2 class="font-medium">{{ __('admin.settings.main') }}</h2>
            @foreach (['name', 'hero_name', 'real_name', 'hero_kicker', 'hero_title', 'location'] as $key)
                <div>
                    <label class="label">{{ __('admin.settings.fields.'.$key) }}</label>
                    @foreach (config('app.locales') as $code => $label)
                        <input x-show="lang === '{{ $code }}'" wire:model="t.{{ $key }}.{{ $code }}" class="input" placeholder="{{ $label }}" lang="{{ $code }}">
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="card space-y-5">
            <h2 class="font-medium">{{ __('admin.settings.about') }}</h2>
            <div>
                <label class="label">{{ __('admin.settings.fields.about_title') }}</label>
                @foreach (config('app.locales') as $code => $label)
                    <input x-show="lang === '{{ $code }}'" wire:model="t.about_title.{{ $code }}" class="input" placeholder="{{ $label }}" lang="{{ $code }}">
                @endforeach
            </div>
            <div>
                <label class="label">{{ __('admin.settings.fields.about_text') }}</label>
                @foreach (config('app.locales') as $code => $label)
                    <textarea x-show="lang === '{{ $code }}'" wire:model="t.about_text.{{ $code }}" rows="9" class="input resize-y" placeholder="{{ $label }}" lang="{{ $code }}"></textarea>
                @endforeach
            </div>
        </div>

        <div class="card space-y-5">
            <h2 class="font-medium">{{ __('admin.settings.contacts') }}</h2>
            @foreach (['email' => 'email', 'phone' => 'tel', 'instagram' => 'url', 'facebook' => 'url', 'youtube' => 'url'] as $key => $type)
                <div>
                    <label class="label">{{ __('admin.settings.fields.'.$key) }}</label>
                    <input type="{{ $type }}" wire:model="p.{{ $key }}" class="input">
                    @error('p.'.$key) <p class="error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="card space-y-5">
            <h2 class="font-medium">YouTube</h2>
            <div>
                <label class="label">{{ __('admin.settings.fields.youtube_channel_id') }}</label>
                <input wire:model="p.youtube_channel_id" class="input" placeholder="UC…">
                <p class="mt-1.5 text-xs text-smoke">{{ __('admin.settings.channel_hint') }}</p>
            </div>
            <label class="flex items-center justify-between gap-4">
                <span>{{ __('admin.settings.fields.youtube_autosync') }}</span>
                <input type="checkbox" wire:model="f.youtube_autosync" class="size-5 accent-amber">
            </label>
            <button type="button" wire:click="syncYouTube" wire:loading.attr="disabled" class="btn-ghost w-full !py-2.5">
                <x-icon name="refresh" class="size-4" wire:loading.class="animate-spin" wire:target="syncYouTube" /> {{ __('admin.youtube.sync') }}
            </button>
            <div>
                <label class="label">{{ __('admin.settings.fields.showreel_url') }}</label>
                <input wire:model="p.showreel_url" class="input" placeholder="https://www.youtube.com/watch?v=…">
                <p class="mt-1.5 text-xs text-smoke">{{ __('admin.settings.showreel_hint') }}</p>
            </div>
        </div>

        <div class="card space-y-5" x-data="{
                busy: false, added: 0, error: null, done: false,
                async run(fromStart) {
                    this.busy = true; this.added = 0; this.error = null; this.done = false;
                    let first = true;
                    while (true) {
                        const r = await $wire.importInstagram(first && fromStart);
                        first = false;
                        this.added += r.added;
                        if (r.error) { this.error = r.error; break; }
                        if (!r.more) { this.done = true; break; }
                    }
                    this.busy = false;
                }
            }">
            <h2 class="font-medium">Instagram</h2>
            @if ($igAccount)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-line px-4 py-3 text-sm">
                    <span>{{ __('admin.instagram.account') }} <b>{{ '@'.$igAccount }}</b></span>
                    <button type="button" wire:click="disconnectInstagram" wire:confirm="{{ __('admin.instagram.confirm_disconnect') }}" class="text-xs text-smoke hover:text-rec">{{ __('admin.instagram.disconnect') }}</button>
                </div>
                <label class="flex items-center justify-between gap-4">
                    <span>{{ __('admin.settings.fields.instagram_autosync') }}</span>
                    <input type="checkbox" wire:model="f.instagram_autosync" class="size-5 accent-amber">
                </label>
                <button type="button" x-on:click="run(true)" x-bind:disabled="busy" class="btn-primary w-full !py-2.5">
                    <x-icon name="refresh" class="size-4" x-bind:class="busy && 'animate-spin'" />
                    <span x-show="!busy">{{ __('admin.instagram.import') }}</span>
                    <span x-show="busy" x-cloak>{{ __('admin.instagram.importing') }} <span x-text="added"></span></span>
                </button>
                <p x-show="done" x-cloak class="text-sm text-amber">{{ __('admin.instagram.done') }} <span x-text="added"></span></p>
                <p x-show="error" x-cloak class="text-sm text-rec" x-text="error"></p>
                <p class="text-xs text-smoke">{{ __('admin.instagram.hint') }}</p>
            @else
                <p class="text-sm text-ash">{{ __('admin.instagram.intro') }}</p>
                <div>
                    <label class="label">{{ __('admin.instagram.token') }}</label>
                    <input wire:model="igToken" type="password" class="input" autocomplete="off" placeholder="IGAA…">
                    @error('igToken') <p class="mt-1.5 text-xs text-rec">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="connectInstagram" wire:loading.attr="disabled" class="btn-ghost w-full !py-2.5">{{ __('admin.instagram.connect') }}</button>
                <a href="https://github.com/790azat/notfilmmaker/blob/main/docs/instagram.md" target="_blank" rel="noopener" class="block text-xs text-amber hover:underline">{{ __('admin.instagram.howto') }}</a>
            @endif
        </div>

        <div class="card space-y-5">
            <h2 class="font-medium">Telegram</h2>
            @if ($tgBot)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-line px-4 py-3 text-sm">
                    <span>{{ __('admin.telegram.bot') }} <a href="https://t.me/{{ $tgBot }}" target="_blank" rel="noopener" class="font-bold hover:text-amber">{{ '@'.$tgBot }}</a></span>
                    <button type="button" wire:click="disconnectTelegram" wire:confirm="{{ __('admin.telegram.confirm_disconnect') }}" class="text-xs text-smoke hover:text-rec">{{ __('admin.telegram.disconnect') }}</button>
                </div>
                <p class="text-sm text-ash">{{ trans_choice('admin.telegram.admins', count(\App\Support\Telegram::admins())) }}</p>
                <a href="{{ \App\Support\Telegram::adminLink() }}" target="_blank" rel="noopener" class="btn-primary w-full !py-2.5"><x-icon name="send" class="size-4" /> {{ __('admin.telegram.link') }}</a>
                <p class="text-xs text-smoke">{{ __('admin.telegram.hint') }}</p>
            @else
                <p class="text-sm text-ash">{{ __('admin.telegram.intro') }}</p>
                <div>
                    <label class="label">{{ __('admin.telegram.token') }}</label>
                    <input wire:model="tgToken" type="password" class="input" autocomplete="off" placeholder="123456789:AA…">
                    @error('tgToken') <p class="mt-1.5 text-xs text-rec">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="connectTelegram" wire:loading.attr="disabled" class="btn-ghost w-full !py-2.5">{{ __('admin.telegram.connect') }}</button>
                <p class="text-xs text-smoke">{{ __('admin.telegram.howto') }}</p>
            @endif
        </div>

        <div class="card space-y-5">
            <h2 class="font-medium">{{ __('admin.settings.images') }}</h2>
            @foreach (['portrait' => 'setPortrait', 'hero_image' => 'setHeroImage', 'og_image' => 'setOgImage'] as $key => $method)
                <div class="flex items-start gap-4">
                    <div class="relative aspect-square w-24 shrink-0 overflow-hidden rounded-xl bg-graphite">
                        @if ($images[$key])
                            <img src="{{ Media::url($images[$key]) }}" alt="" class="size-full object-cover">
                            <button type="button" wire:click="removeImage('{{ $key }}')" wire:confirm="{{ __('admin.confirm') }}" class="absolute top-1 right-1 grid size-6 place-items-center rounded bg-ink/80 text-rec"><x-icon name="trash" class="size-3.5" /></button>
                        @endif
                    </div>
                    <div class="flex-1">
                        <p class="text-sm">{{ __('admin.settings.fields.'.$key) }}</p>
                        <p class="mb-2 text-xs text-smoke">{{ __('admin.settings.hints.'.$key) }}</p>
                        @include('partials.admin.uploader', ['method' => $method, 'folder' => 'site', 'accept' => 'image/*', 'boxClass' => '!p-2'])
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-6">
            <div class="card space-y-5">
                <h2 class="font-medium">{{ __('admin.settings.stats') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['stat_years', 'stat_projects', 'stat_views'] as $key)
                        <div>
                            <label class="label">{{ __('admin.settings.fields.'.$key) }}</label>
                            <input type="number" min="0" wire:model="p.{{ $key }}" class="input">
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-smoke">{{ __('admin.settings.stats_hint') }}</p>
            </div>
            <div class="card space-y-5">
                <h2 class="font-medium">SEO</h2>
                <div>
                    <label class="label">{{ __('admin.settings.fields.meta_description') }}</label>
                    @foreach (config('app.locales') as $code => $label)
                        <textarea x-show="lang === '{{ $code }}'" wire:model="t.meta_description.{{ $code }}" rows="3" class="input resize-y" placeholder="{{ $label }}"></textarea>
                    @endforeach
                </div>
                <label class="flex items-center justify-between gap-4">
                    <span>{{ __('admin.settings.fields.registration_open') }}</span>
                    <input type="checkbox" wire:model="f.registration_open" class="size-5 accent-amber">
                </label>
            </div>
        </div>
    </div>
</form>
