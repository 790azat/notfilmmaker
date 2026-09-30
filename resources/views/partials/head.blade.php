@php
    $siteName = \App\Models\Setting::text('name', 'notfilmmaker');
    $realName = \App\Models\Setting::text('real_name');
    // На главной в заголовке настоящее имя: по нему ищут чаще, чем по нику.
    $pageTitle = isset($title) && $title
        ? $title.' — '.$siteName
        : ($realName && $realName !== $siteName ? $realName.' ('.$siteName.')' : $siteName).' — '.\App\Models\Setting::text('hero_title', 'Director · Cinematographer · Editor');
    $description = $description ?? \App\Models\Setting::text('meta_description', __('site.meta_description'));
    $ogImage = $ogImage ?? \App\Support\Media::url(\App\Models\Setting::get('og_image') ?: \App\Models\Setting::get('portrait'));
    $ogImage = \App\Support\Seo::absolute($ogImage);
    $metaDescription = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($description))), 160);
    $locale = app()->getLocale();
    $noindex = $noindex ?? (! \App\Support\Seo::indexable() || request()->is('admin', 'admin/*', 'login', 'register'));
    $canonical = $canonical ?? \App\Support\Seo::localized($locale);
    $schema = array_merge([\App\Support\Seo::website(), \App\Support\Seo::person()], $schema ?? []);
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="author" content="{{ $realName ?: $siteName }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-video-preview:-1">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach (\App\Support\Seo::alternates() as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
@endif
<meta name="theme-color" content="#0b0b0c">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ \App\Support\Seo::OG_LOCALES[$locale] ?? $locale }}">
@foreach (\App\Support\Seo::OG_LOCALES as $code => $ogLocale)
    @if ($code !== $locale)
        <meta property="og:locale:alternate" content="{{ $ogLocale }}">
    @endif
@endforeach
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:alt" content="{{ $title ?? $siteName }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $ogImage }}">
@else
    <meta name="twitter:card" content="summary">
@endif
@unless ($noindex)
    <script type="application/ld+json">{!! \App\Support\Seo::jsonLd($schema) !!}</script>
@endunless
<link rel="sitemap" type="application/xml" href="/sitemap.xml">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Noto+Sans+Armenian:wdth,wght@62.5..100,300..700&family=Oswald:wght@300..700&display=swap" rel="stylesheet">
<script>window.__portfolio = { blob: @json(\App\Support\Blob::enabled()) };</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
