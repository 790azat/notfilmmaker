<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden lg:block">
            @php $bg = \App\Models\Work::published()->whereNotNull('youtube_id')->ordered()->value('youtube_id'); @endphp
            @if ($bg)
                <img src="{{ \App\Support\YouTube::thumbnail($bg) }}" alt="" class="absolute inset-0 size-full object-cover opacity-50" onerror="if(!this.dataset.f){this.dataset.f=1;this.src='{{ \App\Support\YouTube::thumbnail($bg, 'hqdefault') }}'}else{this.style.display='none'}">
            @endif
            <div class="absolute inset-0 bg-gradient-to-tr from-ink via-ink/60 to-transparent"></div>
            <div class="absolute bottom-12 left-12">
                <p class="kicker">{{ __('site.hero.kicker') }}</p>
                <p class="display mt-5 text-7xl">@include('partials.hero-name')</p>
            </div>
        </div>
        <div class="flex flex-col">
            <div class="flex items-center justify-between p-6 sm:p-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm text-ash hover:text-bone"><x-icon name="arrow-left" class="size-4" /> {{ __('site.nav.home') }}</a>
                @include('partials.lang-switch')
            </div>
            <div class="flex flex-1 items-center justify-center px-6 pb-16">
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
