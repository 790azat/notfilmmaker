@php
    $parts = preg_split('/\s+/', trim(\App\Models\Setting::text('hero_name', 'not filmmaker')), 2);
@endphp
{{-- «not» зачёркнуто красным, как в логотипе: not filmmaker --}}
<span class="relative inline-block text-amber italic after:absolute after:inset-x-[-4%] after:top-[54%] after:h-[0.035em] after:-rotate-[4deg] after:bg-amber">{{ $parts[0] ?? '' }}</span>
@if (! empty($parts[1]))
    <span class="block pl-[0.35em] sm:whitespace-nowrap">{{ $parts[1] }}</span>
@endif
