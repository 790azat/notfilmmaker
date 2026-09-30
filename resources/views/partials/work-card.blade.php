@php
    $cover = $work->coverUrl();
    $large = $large ?? false;
    $file = $work->videoFileUrl();
    $tall = in_array($work->category, ['photo', 'reels'], true);
@endphp
<article class="group reveal relative" wire:key="work-{{ $work->id }}">
    <a href="{{ route('works.show', $work) }}" wire:navigate class="block">
        <div @if ($file) x-data x-on:mouseenter="$refs.preview.play().catch(() => {})" x-on:mouseleave="$refs.preview.pause()" @endif
             class="relative overflow-hidden rounded-md bg-graphite {{ $large ? 'aspect-[16/10]' : ($tall ? 'aspect-[4/5]' : 'aspect-video') }}">
            @if ($cover)
                <img src="{{ $cover }}" alt="{{ $work->title }}" loading="lazy"
                     @if ($work->youtube_id && ! $work->cover) onerror="if(!this.dataset.f){this.dataset.f=1;this.src='{{ \App\Support\YouTube::thumbnail($work->youtube_id, 'hqdefault') }}'}else{this.style.display='none'}" @endif
                     class="size-full object-cover transition duration-[1.2s] ease-out group-hover:scale-105">
            @elseif ($file)
                <video src="{{ $file }}#t=0.5" muted playsinline preload="metadata" class="size-full object-cover"></video>
            @else
                <div class="grid size-full place-items-center bg-gradient-to-br from-graphite to-coal">
                    <x-icon name="film" class="size-10 text-smoke" />
                </div>
            @endif
            @if ($file)
                {{-- Живое превью: видео начинает играть без звука при наведении. --}}
                <video x-ref="preview" src="{{ $file }}" muted loop playsinline preload="none"
                       class="absolute inset-0 size-full object-cover opacity-0 transition duration-700 group-hover:opacity-100"></video>
            @endif

            @if ($work->hasVideo())
                <button type="button"
                        x-on:click.prevent.stop="$dispatch('play-video', { src: @js($work->embedUrl(true) ?? $work->videoFileUrl()) })"
                        class="absolute top-1/2 left-1/2 grid size-16 -translate-x-1/2 -translate-y-1/2 scale-90 place-items-center rounded-full bg-white/95 text-black opacity-0 shadow-2xl transition duration-500 group-hover:scale-100 group-hover:opacity-100"
                        aria-label="{{ __('site.works.watch') }}">
                    <x-icon name="play" class="ml-1 size-6" />
                </button>
            @endif

        </div>
        {{-- Подпись под кадром, как в каталоге выставки --}}
        <div class="mt-4 flex items-baseline justify-between gap-4 border-t border-line pt-3">
            <h3 class="display {{ $large ? 'text-3xl sm:text-4xl' : 'text-2xl sm:text-3xl' }} transition group-hover:text-amber">{{ $work->title }}</h3>
            <p class="shrink-0 font-mono text-[10px] tracking-[0.2em] text-ash uppercase">{{ $work->categoryLabel() }}@if ($work->year) · {{ $work->year }}@endif</p>
        </div>
    </a>
</article>
