@php
    $siteName = \App\Models\Setting::text('name', 'notfilmmaker');
    $pageTitle = isset($title) && $title ? $title.' — '.$siteName : $siteName.' — '.\App\Models\Setting::text('hero_title', 'Director · Cinematographer · Editor');
    $description = $description ?? \App\Models\Setting::text('meta_description', __('site.meta_description'));
    $ogImage = $ogImage ?? \App\Support\Media::url(\App\Models\Setting::get('og_image') ?: \App\Models\Setting::get('portrait'));
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160) }}">
<meta name="theme-color" content="#0b0b0c">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 200) }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Noto+Sans+Armenian:wdth,wght@62.5..100,300..700&family=Oswald:wght@300..700&display=swap" rel="stylesheet">
<script>window.__portfolio = { blob: @json(\App\Support\Blob::enabled()) };</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
