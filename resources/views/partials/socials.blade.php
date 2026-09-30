@php
    $links = array_filter([
        'instagram' => \App\Models\Setting::get('instagram'),
        'facebook' => \App\Models\Setting::get('facebook'),
        'youtube' => \App\Models\Setting::get('youtube'),
    ]);
@endphp
<div class="flex items-center gap-2 {{ $class ?? '' }}">
    @foreach ($links as $network => $url)
        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"
           class="grid size-10 place-items-center rounded-full border border-line text-ash transition hover:border-amber hover:text-amber">
            <x-icon :name="$network" class="size-[18px]" />
        </a>
    @endforeach
</div>
