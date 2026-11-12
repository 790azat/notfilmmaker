<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => __('alternative.title'), 'description' => __('alternative.description'), 'assets' => ['resources/css/desk.css', 'resources/js/desk/main.js']])
</head>
<body class="desk-page">
    <canvas id="desk-canvas" aria-hidden="true"></canvas>

    <div id="desk-loader" class="desk-loader">
        <div class="desk-loader__inner">
            <p class="desk-brand">{{ $site['name'] }}</p>
            <div class="desk-loader__bar"><span id="desk-progress"></span></div>
            <p class="desk-loader__text" id="desk-loader-text">{{ __('alternative.loading') }}</p>
            <button type="button" id="desk-enter" class="desk-btn desk-btn--primary" hidden>{{ __('alternative.enter') }}</button>
            <p class="desk-loader__note">{{ __('alternative.sound_note') }}</p>
        </div>
    </div>

    <header class="desk-top">
        <a href="{{ route('home') }}" class="desk-brand desk-brand--small">{{ $site['name'] }}</a>
        <nav class="desk-top__right">
            @foreach (config('app.locales') as $code => $label)
                <a href="{{ route('locale', $code) }}" class="desk-lang {{ app()->getLocale() === $code ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('home') }}" class="desk-btn desk-btn--ghost">{{ __('alternative.main_site') }} ↗</a>
        </nav>
    </header>

    <div id="desk-tooltip" class="desk-tooltip" hidden></div>
    <p id="desk-hint" class="desk-hint"></p>

    {{-- Кнопки предметов: для клавиатуры, экранных дикторов и быстрой навигации на телефоне. --}}
    <nav id="desk-dock" class="desk-dock" aria-label="{{ __('alternative.title') }}"></nav>

    <button type="button" id="desk-back" class="desk-btn desk-btn--ghost desk-back" hidden>← {{ __('alternative.back') }}</button>

    <div id="desk-player" class="desk-player" hidden></div>
    <div id="desk-panel" class="desk-panel" role="dialog" aria-modal="false" hidden></div>
    <div id="desk-screen" class="desk-screen" hidden></div>

    <noscript>
        <div class="desk-fallback"><p>{{ __('alternative.no_webgl') }}</p><a class="desk-btn desk-btn--primary" href="{{ route('home') }}">{{ __('alternative.main_site') }}</a></div>
    </noscript>
    <div id="desk-fallback" class="desk-fallback" hidden>
        <p>{{ __('alternative.no_webgl') }}</p>
        <a class="desk-btn desk-btn--primary" href="{{ route('home') }}">{{ __('alternative.main_site') }}</a>
    </div>

    <script type="application/json" id="desk-data">@json($data)</script>
</body>
</html>
