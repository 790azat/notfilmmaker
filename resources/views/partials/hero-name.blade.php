@php
    $parts = preg_split('/\s+/', trim(\App\Models\Setting::text('hero_name', 'not filmmaker')), 2);
@endphp
<span class="block">{{ $parts[0] ?? '' }}</span>
@if (! empty($parts[1]))
    <span class="block text-transparent [-webkit-text-stroke:1.5px_var(--color-bone)]">{{ $parts[1] }}</span>
@endif
