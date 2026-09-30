@php use App\Models\Setting; use App\Support\YouTube; use App\Support\Media; @endphp
<div>
    {{-- HERO --}}
    <section class="relative flex min-h-[100svh] items-end overflow-hidden bg-ink">
        @if ($reels->count() >= 3)
            {{-- Живая стена из рилсов: на компьютере играют без звука, на телефоне — кадры. Шоурил с YouTube остаётся на кнопке. --}}
            <div class="absolute inset-0 flex gap-2 opacity-45 sm:gap-3" wire:ignore aria-hidden="true">
                @foreach ($reels->take(5) as $reel)
                    <div class="relative h-full flex-1 overflow-hidden {{ $loop->index >= 3 ? 'hidden lg:block' : '' }}">
                        <video src="{{ $reel->videoFileUrl() }}" poster="{{ $reel->coverUrl() }}" muted loop playsinline preload="none"
                               x-data x-init="if (window.matchMedia('(min-width: 640px)').matches) { $el.preload = 'auto'; $el.play().catch(() => {}) }"
                               class="size-full object-cover"></video>
                    </div>
                @endforeach
            </div>
        @elseif ($showreel)
            <img src="{{ YouTube::thumbnail($showreel) }}" alt="" class="absolute inset-0 size-full object-cover opacity-40" onerror="if(!this.dataset.f){this.dataset.f=1;this.src='{{ YouTube::thumbnail($showreel, 'hqdefault') }}'}else{this.style.display='none'}">
            <div class="absolute inset-0 overflow-hidden" wire:ignore>
                <iframe class="video-bg opacity-60" src="{{ YouTube::background($showreel) }}" title="Showreel" allow="autoplay; encrypted-media" tabindex="-1"></iframe>
            </div>
        @elseif ($hero = Setting::get('hero_image'))
            <img src="{{ Media::url($hero) }}" alt="" class="absolute inset-0 size-full object-cover opacity-50">
        @else
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_30%_20%,rgba(224,168,78,.18),transparent_55%),radial-gradient(ellipse_at_80%_80%,rgba(239,68,68,.10),transparent_50%)]"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/40 to-ink/60"></div>

        {{-- Видоискатель --}}
        <div class="pointer-events-none absolute inset-5 hidden sm:block lg:inset-10" aria-hidden="true"
             x-data="{ t: 0 }" x-init="setInterval(() => t++, 1000 / 24)">
            <span class="absolute top-16 left-0 size-8 border-t border-l border-bone/40"></span>
            <span class="absolute top-16 right-0 size-8 border-t border-r border-bone/40"></span>
            <span class="absolute bottom-0 left-0 size-8 border-b border-l border-bone/40"></span>
            <span class="absolute right-0 bottom-0 size-8 border-r border-b border-bone/40"></span>
            <div class="absolute top-20 left-12 flex items-center gap-2 font-mono text-xs tracking-widest text-bone/70">
                <span class="size-2 rounded-full bg-rec animate-rec"></span> REC
            </div>
            <div class="absolute top-20 right-12 font-mono text-xs tracking-widest text-bone/70"
                 x-text="[Math.floor(t/86400), Math.floor(t/1440)%60, Math.floor(t/24)%60, t%24].map(n => String(n).padStart(2,'0')).join(':')"></div>
            <div class="absolute bottom-6 left-12 font-mono text-[10px] tracking-[0.3em] text-bone/50">4K · 24 FPS · ISO 800</div>
        </div>

        <div class="container-x relative z-10 pb-20 pt-40 sm:pb-28">
            <p class="kicker reveal">{{ Setting::text('hero_kicker', __('site.hero.kicker')) }}</p>
            <h1 class="display reveal mt-6 text-[clamp(3rem,10.5vw,11rem)] [overflow-wrap:anywhere]">
                @include('partials.hero-name')
            </h1>
            <div class="reveal mt-8 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div x-data="{ roles: @js(__('site.hero.roles')), i: 0 }" x-init="setInterval(() => i = (i + 1) % roles.length, 2200)"
                     class="flex items-baseline gap-3 text-xl sm:text-2xl">
                    <span class="text-ash">/</span>
                    <span class="relative inline-block h-[1.3em] min-w-[12ch] overflow-hidden">
                        <template x-for="(role, idx) in roles" :key="idx">
                            <span class="absolute inset-0 transition duration-700"
                                  :class="idx === i ? 'translate-y-0 opacity-100' : (idx < i ? '-translate-y-full opacity-0' : 'translate-y-full opacity-0')"
                                  x-text="role"></span>
                        </template>
                    </span>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($showreel)
                        <button type="button" class="btn-primary" x-on:click="$dispatch('play-video', { src: @js(YouTube::embed($showreel, true)) })">
                            <x-icon name="play" class="size-4" /> {{ __('site.hero.play_reel') }}
                        </button>
                    @endif
                    <a href="{{ route('works.index') }}" wire:navigate class="{{ $showreel ? 'btn-ghost' : 'btn-primary' }}">{{ __('site.hero.cta_works') }} <x-icon name="arrow-right" class="size-4" /></a>
                    <a href="{{ route('contact') }}" wire:navigate class="btn-ghost">{{ __('site.hero.cta_contact') }}</a>
                </div>
            </div>
        </div>

        <a href="#featured" class="absolute bottom-8 left-1/2 z-10 hidden -translate-x-1/2 flex-col items-center gap-3 text-[10px] tracking-[0.4em] text-ash uppercase lg:flex">
            {{ __('site.hero.scroll') }}
            <span class="h-12 w-px bg-gradient-to-b from-bone/60 to-transparent"></span>
        </a>
    </section>

    {{-- Плёнка из кадров --}}
    @php $filmFrames = $featured->merge($reels)->unique('id')->filter(fn ($w) => $w->coverUrl())->values()->take(10); @endphp
    @if ($filmFrames->count() >= 4)
        <div class="overflow-hidden border-y border-line bg-black py-4" aria-hidden="true">
            <div class="film-holes"></div>
            <div class="flex w-max animate-marquee gap-2.5 py-3.5">
                @for ($k = 0; $k < 2; $k++)
                    @foreach ($filmFrames as $i => $frame)
                        <div class="relative h-32 w-52 shrink-0 overflow-hidden sm:h-40 sm:w-60">
                            <img src="{{ $frame->coverUrl() }}" alt="" loading="lazy" class="size-full object-cover">
                            <span class="absolute bottom-1.5 left-2 font-mono text-[9px] tracking-[0.2em] text-white/80">{{ 12 + $i }}A</span>
                        </div>
                    @endforeach
                @endfor
            </div>
            <div class="film-holes"></div>
        </div>
    @endif

    {{-- Избранные работы --}}
    <section id="featured" class="py-24 sm:py-32">
        <div class="container-x">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="reveal">
                    <p class="kicker">{{ __('site.home.featured_kicker') }}</p>
                    <h2 class="display mt-5 max-w-3xl text-5xl sm:text-7xl">{{ __('site.home.featured_title') }}</h2>
                </div>
                <a href="{{ route('works.index') }}" wire:navigate class="btn-ghost reveal self-start sm:self-auto">{{ __('site.home.all_works') }} <x-icon name="arrow-right" class="size-4" /></a>
            </div>

            @if ($featured->isNotEmpty())
                <div class="mt-14 grid gap-5 md:grid-cols-2 lg:gap-6">
                    @foreach ($featured as $i => $work)
                        <div class="{{ $i === 0 ? 'md:col-span-2' : '' }}">
                            @include('partials.work-card', ['work' => $work, 'large' => $i === 0])
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-14 grid place-items-center rounded-2xl border border-dashed border-line py-24 text-ash">
                    <x-icon name="film" class="size-10" />
                    <p class="mt-4">{{ __('site.works.empty') }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- Цифры --}}
    <section class="border-y border-line bg-coal">
        <div class="container-x grid grid-cols-1 divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach ($stats as $key => $value)
                @continue($value <= 0)
                <div class="reveal px-2 py-10 sm:px-10 sm:py-14" x-data="{ n: 0, target: {{ $value }} }"
                     x-init="new IntersectionObserver((entries, obs) => { if (!entries[0].isIntersecting) return; obs.disconnect(); let s = performance.now(); const tick = (now) => { const p = Math.min(1, (now - s) / 1600); n = Math.round(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick) }; requestAnimationFrame(tick) }).observe($el)">
                    <p class="display text-6xl text-amber sm:text-7xl">
                        <span x-text="n >= 1000000 ? (n / 1000000).toFixed(1) + 'M' : (n >= 10000 ? Math.round(n / 1000) + 'K' : n)">{{ $value }}</span>+
                    </p>
                    <p class="mt-3 text-sm tracking-wider text-ash uppercase">{{ __('site.home.stats.'.$key) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Услуги --}}
    <section class="py-24 sm:py-32">
        <div class="container-x">
            <div class="reveal max-w-3xl">
                <p class="kicker">{{ __('site.home.services_kicker') }}</p>
                <h2 class="display mt-5 text-5xl sm:text-7xl">{{ __('site.home.services_title') }}</h2>
            </div>
            <div class="mt-16 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('site.services') as $n => $service)
                    <div class="group reveal relative bg-ink p-8 transition duration-500 hover:bg-coal sm:p-10">
                        <span class="absolute top-8 right-8 font-mono text-xs text-smoke">0{{ $n + 1 }}</span>
                        <span class="grid size-14 place-items-center rounded-2xl border border-line text-amber transition duration-500 group-hover:border-amber group-hover:bg-amber group-hover:text-ink">
                            <x-icon :name="$service['icon']" class="size-6" />
                        </span>
                        <h3 class="display mt-8 text-3xl">{{ $service['title'] }}</h3>
                        <p class="mt-4 leading-relaxed text-ash">{{ $service['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Обо мне --}}
    <section class="overflow-hidden border-t border-line py-24 sm:py-32">
        <div class="container-x grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
            <div class="reveal relative">
                @if ($portrait = Setting::get('portrait'))
                    <img src="{{ Media::url($portrait) }}" alt="{{ Setting::text('name') }}" class="aspect-[4/5] w-full rounded-2xl object-cover grayscale transition duration-700 hover:grayscale-0" loading="lazy">
                @else
                    <div class="grid aspect-[4/5] grid-cols-2 gap-3">
                        @foreach ($latestVideos->take(4) as $v)
                            <img src="{{ YouTube::thumbnail($v->youtube_id, 'hqdefault') }}" alt="" class="size-full rounded-xl object-cover {{ $loop->odd ? 'translate-y-8' : '' }}" loading="lazy">
                        @endforeach
                    </div>
                @endif
                <div class="absolute -right-4 -bottom-6 hidden rounded-2xl border border-line bg-ink/90 px-6 py-5 backdrop-blur sm:block">
                    <p class="font-mono text-xs tracking-widest text-ash">@not_filmmaker</p>
                    <p class="display mt-1 text-2xl">{{ Setting::text('location', 'Yerevan, Armenia') }}</p>
                </div>
            </div>
            <div class="reveal">
                <p class="kicker">{{ __('site.home.about_kicker') }}</p>
                <h2 class="display mt-5 text-5xl sm:text-6xl">{{ Setting::text('about_title', Setting::text('name')) }}</h2>
                <div class="prose-film mt-8">
                    {!! nl2br(e(\Illuminate\Support\Str::limit(Setting::text('about_text', ''), 520))) !!}
                </div>
                <div class="mt-10 flex flex-wrap items-center gap-6">
                    <a href="{{ route('about') }}" wire:navigate class="btn-primary">{{ __('site.home.about_more') }} <x-icon name="arrow-right" class="size-4" /></a>
                    @include('partials.socials')
                </div>
            </div>
        </div>
    </section>

    {{-- Рилсы --}}
    @if ($reels->count() >= 3)
        <section class="border-t border-line py-24 sm:py-32">
            <div class="container-x flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="reveal">
                    <p class="kicker">Instagram</p>
                    <h2 class="display mt-5 text-5xl sm:text-7xl">{{ __('site.home.reels_title') }}</h2>
                </div>
                <a href="{{ route('works.index', ['c' => 'reels']) }}" wire:navigate class="btn-ghost reveal self-start">{{ __('site.home.all_reels') }} <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            <div class="mt-14 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-6 sm:px-8 lg:px-[max(3rem,calc((100vw-1400px)/2+3rem))] [scrollbar-width:thin]">
                @foreach ($reels as $reel)
                    <a href="{{ route('works.show', $reel) }}" wire:navigate
                       x-data x-on:mouseenter="$refs.v.play().catch(() => {})" x-on:mouseleave="$refs.v.pause()"
                       class="group relative aspect-[9/16] w-[62vw] shrink-0 snap-start overflow-hidden rounded-2xl bg-graphite sm:w-[260px]">
                        <img src="{{ $reel->coverUrl() }}" alt="{{ $reel->title }}" loading="lazy" class="absolute inset-0 size-full object-cover">
                        <video x-ref="v" src="{{ $reel->videoFileUrl() }}" muted loop playsinline preload="none"
                               class="absolute inset-0 size-full object-cover opacity-0 transition duration-500 group-hover:opacity-100"></video>
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-transparent to-transparent"></div>
                        <p class="absolute inset-x-0 bottom-0 p-4 text-sm font-medium">{{ $reel->title }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- YouTube --}}
    @if ($latestVideos->isNotEmpty())
        <section class="border-t border-line bg-coal py-24 sm:py-32">
            <div class="container-x flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="reveal">
                    <p class="kicker">{{ __('site.home.youtube_kicker') }}</p>
                    <h2 class="display mt-5 text-5xl sm:text-7xl">{{ __('site.home.youtube_title') }}</h2>
                </div>
                @if ($yt = Setting::get('youtube'))
                    <a href="{{ $yt }}?sub_confirmation=1" target="_blank" rel="noopener" class="btn bg-rec text-white hover:bg-bone hover:text-ink reveal self-start">
                        <x-icon name="youtube" class="size-5" /> {{ __('site.home.subscribe') }}
                    </a>
                @endif
            </div>
            <div class="mt-14 flex snap-x snap-mandatory gap-5 overflow-x-auto px-5 pb-6 sm:px-8 lg:px-[max(3rem,calc((100vw-1400px)/2+3rem))] [scrollbar-width:thin]">
                @foreach ($latestVideos as $video)
                    <button type="button" x-on:click="$dispatch('play-video', { src: @js($video->embedUrl(true)) })"
                            class="group w-[80vw] shrink-0 snap-start text-left sm:w-[420px]">
                        <div class="relative aspect-video overflow-hidden rounded-xl bg-graphite">
                            <img src="{{ YouTube::thumbnail($video->youtube_id, 'hqdefault') }}" alt="{{ $video->title }}" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105">
                            <span class="absolute inset-0 grid place-items-center bg-ink/30 opacity-0 transition group-hover:opacity-100">
                                <span class="grid size-14 place-items-center rounded-full bg-rec text-white"><x-icon name="play" class="ml-0.5 size-5" /></span>
                            </span>
                        </div>
                        <p class="mt-4 line-clamp-2 font-medium leading-snug">{{ $video->title }}</p>
                        <p class="mt-1 text-xs text-smoke">{{ $video->published_at?->translatedFormat('j F Y') }}@if ($video->views) · {{ number_format($video->views, 0, '.', ' ') }} {{ __('site.works.views') }}@endif</p>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Призыв --}}
    <section class="relative overflow-hidden py-28 sm:py-40">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(224,168,78,.14),transparent_60%)]"></div>
        <div class="container-x relative text-center">
            <h2 class="display reveal mx-auto max-w-5xl text-5xl sm:text-8xl">{{ __('site.home.cta_title') }}</h2>
            <p class="reveal mx-auto mt-8 max-w-xl text-lg text-ash">{{ __('site.home.cta_text') }}</p>
            <div class="reveal mt-12 flex justify-center">
                <a href="{{ route('contact') }}" wire:navigate class="btn-primary !px-10 !py-5 text-base">{{ __('site.hero.cta_contact') }} <x-icon name="arrow-up-right" class="size-5" /></a>
            </div>
        </div>
    </section>
</div>
