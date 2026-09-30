<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen overflow-x-hidden"
      x-data="{ video: null, menu: false }"
      x-on:play-video.window="video = $event.detail.src"
      x-on:keydown.escape.window="video = null; menu = false">
    <div class="grain" aria-hidden="true"></div>

    @php $siteName = \App\Models\Setting::text('name', 'notfilmmaker'); @endphp

    <header x-data="{ scrolled: false }" x-on:scroll.window="scrolled = window.scrollY > 40"
            :class="scrolled ? 'bg-ink/80 backdrop-blur-xl border-line' : 'border-transparent'"
            class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-500">
        <div class="container-x flex h-20 items-center justify-between gap-6">
            <a href="{{ route('home') }}" wire:navigate class="group flex items-center gap-3">
                <span class="relative grid size-9 place-items-center rounded-full border border-bone/30">
                    <span class="size-2 rounded-full bg-rec animate-rec"></span>
                </span>
                <span class="display text-xl leading-none normal-case">{{ $siteName }}</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm lg:flex">
                @foreach (['home' => 'home', 'works.index' => 'works', 'about' => 'about', 'contact' => 'contact'] as $route => $key)
                    <a href="{{ route($route) }}" wire:navigate
                       class="relative py-2 transition hover:text-bone {{ request()->routeIs($route === 'works.index' ? 'works.*' : $route) ? 'text-bone after:absolute after:inset-x-0 after:-bottom-0.5 after:h-px after:bg-amber' : 'text-ash' }}">
                        {{ __('site.nav.'.$key) }}
                    </a>
                @endforeach
            </nav>

            <div class="hidden items-center gap-4 lg:flex">
                @include('partials.lang-switch')
                @auth
                    @if (auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}" class="btn-ghost !px-4 !py-2 text-xs">{{ __('site.nav.admin') }}</a>
                    @endif
                @endauth
            </div>

            <button type="button" class="grid size-11 place-items-center rounded-full border border-line lg:hidden" x-on:click="menu = true" aria-label="{{ __('site.nav.menu') }}">
                <x-icon name="menu" />
            </button>
        </div>
    </header>

    {{-- Мобильное меню --}}
    <div x-cloak x-show="menu" x-transition.opacity class="fixed inset-0 z-[70] flex flex-col bg-ink lg:hidden">
        <div class="container-x flex h-20 items-center justify-between">
            <span class="display text-xl normal-case">{{ $siteName }}</span>
            <button type="button" class="grid size-11 place-items-center rounded-full border border-line" x-on:click="menu = false" aria-label="{{ __('site.nav.close') }}">
                <x-icon name="x" />
            </button>
        </div>
        <nav class="container-x flex flex-1 flex-col justify-center gap-2">
            @foreach (['home' => 'home', 'works.index' => 'works', 'about' => 'about', 'contact' => 'contact'] as $route => $key)
                <a href="{{ route($route) }}" wire:navigate x-on:click="menu = false" class="display text-5xl sm:text-6xl py-2 transition hover:text-amber">{{ __('site.nav.'.$key) }}</a>
            @endforeach
        </nav>
        <div class="container-x flex items-center justify-between py-8">
            @include('partials.lang-switch')
            @include('partials.socials')
        </div>
    </div>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="container-x grid gap-10 py-16 md:grid-cols-3">
            <div>
                <p class="display text-3xl normal-case">{{ $siteName }}</p>
                <p class="mt-3 max-w-xs text-sm text-ash">{{ __('site.footer.tagline') }}</p>
            </div>
            <nav class="grid grid-cols-2 gap-3 text-sm text-ash">
                <a href="{{ route('works.index') }}" wire:navigate class="hover:text-bone">{{ __('site.nav.works') }}</a>
                <a href="{{ route('about') }}" wire:navigate class="hover:text-bone">{{ __('site.nav.about') }}</a>
                <a href="{{ route('contact') }}" wire:navigate class="hover:text-bone">{{ __('site.nav.contact') }}</a>
                @auth
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-bone">{{ __('site.nav.admin') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-bone">{{ __('site.nav.login') }}</a>
                @endauth
            </nav>
            <div class="flex flex-col gap-4 md:items-end">
                @include('partials.socials')
                @include('partials.lang-switch')
            </div>
        </div>
        <div class="container-x flex flex-col gap-2 border-t border-line py-6 text-xs text-smoke sm:flex-row sm:justify-between">
            <span>© {{ date('Y') }} {{ $siteName }}. {{ __('site.footer.rights') }}</span>
            <span>{{ \App\Models\Setting::text('location', 'Yerevan, Armenia') }}</span>
        </div>
    </footer>

    {{-- Плеер во всплывающем окне --}}
    <div x-cloak x-show="video" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-black/95 p-4 sm:p-10" x-on:click.self="video = null">
        <button type="button" class="absolute top-5 right-5 grid size-12 place-items-center rounded-full border border-bone/30 text-bone hover:bg-bone hover:text-ink" x-on:click="video = null" aria-label="{{ __('site.nav.close') }}">
            <x-icon name="x" />
        </button>
        <div class="aspect-video w-full max-w-6xl overflow-hidden rounded-xl bg-black shadow-2xl">
            <template x-if="video && !video.match(/\.(mp4|webm|mov|m4v)(\?|$)/i)">
                <iframe :src="video" class="size-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            </template>
            <template x-if="video && video.match(/\.(mp4|webm|mov|m4v)(\?|$)/i)">
                <video :src="video" class="size-full" controls autoplay playsinline></video>
            </template>
        </div>
    </div>
</body>
</html>
