@php $images = $work->media->where('type', 'image')->values(); $videos = $work->media->where('type', 'video')->values(); @endphp
<div x-data="{ lightbox: null, images: @js($images->map(fn ($m) => $m->url())->all()) }"
     x-on:keydown.arrow-right.window="if (lightbox !== null) lightbox = (lightbox + 1) % images.length"
     x-on:keydown.arrow-left.window="if (lightbox !== null) lightbox = (lightbox - 1 + images.length) % images.length"
     x-on:keydown.escape.window="lightbox = null">
    <section class="pt-32 sm:pt-40">
        <div class="container-x">
            <a href="{{ route('works.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-ash transition hover:text-bone">
                <x-icon name="arrow-left" class="size-4" /> {{ __('site.works.back') }}
            </a>
            <div class="mt-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="kicker">{{ $work->categoryLabel() }}@if ($work->year) · {{ $work->year }}@endif</p>
                    <h1 class="display mt-5 max-w-5xl text-5xl sm:text-7xl lg:text-8xl">{{ $work->title }}</h1>
                </div>
                @if (! $work->is_published)
                    <span class="self-start rounded-full bg-rec/15 px-4 py-1.5 text-xs text-rec">{{ __('site.works.draft') }}</span>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-12">
        <div class="container-x">
            @if ($embed = $work->embedUrl())
                <div class="aspect-video overflow-hidden rounded-2xl bg-black shadow-2xl">
                    <iframe src="{{ $embed }}" class="size-full" title="{{ $work->title }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
                </div>
            @elseif ($file = $work->videoFileUrl())
                <video src="{{ $file }}" poster="{{ $work->cover ? $work->coverUrl() : '' }}" class="aspect-video w-full rounded-2xl bg-black" controls playsinline preload="metadata"></video>
            @elseif ($cover = $work->coverUrl())
                <img src="{{ $cover }}" alt="{{ $work->title }}" class="max-h-[85vh] w-full rounded-2xl object-cover">
            @endif
        </div>
    </section>

    <section class="py-16 sm:py-24">
        <div class="container-x grid gap-14 lg:grid-cols-[1fr_360px] lg:gap-20">
            <div class="prose-film">
                @if ($work->excerpt)
                    <p class="text-2xl leading-snug text-bone">{{ $work->excerpt }}</p>
                @endif
                @if ($work->description && $work->description !== $work->excerpt)
                    {!! nl2br(e($work->description), false) !!}
                @endif
            </div>
            <aside>
                <dl class="divide-y divide-line border-y border-line">
                    @foreach (['category' => $work->categoryLabel(), 'year' => $work->year, 'role' => $work->role, 'client' => $work->client] as $label => $value)
                        @if ($value)
                            <div class="flex justify-between gap-6 py-4 text-sm">
                                <dt class="text-ash">{{ __('site.works.'.$label) }}</dt>
                                <dd class="text-right">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
                @if ($work->youtube_id)
                    <a href="https://www.youtube.com/watch?v={{ $work->youtube_id }}" target="_blank" rel="noopener" class="btn-ghost mt-6 w-full">
                        <x-icon name="youtube" /> {{ __('site.works.watch_on_youtube') }}
                    </a>
                @endif
            </aside>
        </div>
    </section>

    @if ($videos->isNotEmpty())
        <section class="pb-16">
            <div class="container-x grid gap-5 md:grid-cols-2">
                @foreach ($videos as $v)
                    <video src="{{ $v->url() }}" class="aspect-video w-full rounded-2xl bg-black" controls playsinline preload="metadata"></video>
                @endforeach
            </div>
        </section>
    @endif

    @if ($images->isNotEmpty())
        <section class="pb-24">
            <div class="container-x">
                <h2 class="display mb-8 text-3xl">{{ __('site.works.gallery') }}</h2>
                <div class="columns-1 gap-5 sm:columns-2 lg:columns-3">
                    @foreach ($images as $i => $img)
                        <button type="button" x-on:click="lightbox = {{ $i }}" class="reveal mb-5 block w-full overflow-hidden rounded-xl">
                            <img src="{{ $img->url() }}" alt="{{ $work->title }} — {{ $i + 1 }}" loading="lazy" class="w-full transition duration-700 hover:scale-105">
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($next && $next->id !== $work->id)
        <a href="{{ route('works.show', $next) }}" wire:navigate class="group relative block overflow-hidden border-y border-line">
            @if ($c = $next->coverUrl())
                <img src="{{ $c }}" alt="" class="absolute inset-0 size-full object-cover opacity-20 transition duration-1000 group-hover:scale-105 group-hover:opacity-40">
            @endif
            <div class="container-x relative flex items-center justify-between gap-6 py-16 sm:py-24">
                <div>
                    <p class="kicker">{{ __('site.works.next') }}</p>
                    <p class="display mt-4 text-4xl sm:text-6xl">{{ $next->title }}</p>
                </div>
                <span class="grid size-16 shrink-0 place-items-center rounded-full border border-bone/30 transition group-hover:bg-amber group-hover:text-ink sm:size-20">
                    <x-icon name="arrow-right" class="size-6" />
                </span>
            </div>
        </a>
    @endif

    @if ($related->isNotEmpty())
        <section class="py-24">
            <div class="container-x">
                <h2 class="display text-4xl sm:text-5xl">{{ __('site.works.more') }}</h2>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $r)
                        @include('partials.work-card', ['work' => $r])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div x-cloak x-show="lightbox !== null" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-black/95 p-4" x-on:click.self="lightbox = null">
        <img :src="images[lightbox]" class="max-h-[90vh] max-w-full rounded-lg object-contain" alt="">
        <button type="button" class="absolute top-5 right-5 grid size-12 place-items-center rounded-full border border-bone/30" x-on:click="lightbox = null"><x-icon name="x" /></button>
        <button type="button" class="absolute left-4 grid size-12 place-items-center rounded-full border border-bone/30 bg-ink/50" x-on:click="lightbox = (lightbox - 1 + images.length) % images.length"><x-icon name="arrow-left" /></button>
        <button type="button" class="absolute right-4 grid size-12 place-items-center rounded-full border border-bone/30 bg-ink/50" x-on:click="lightbox = (lightbox + 1) % images.length"><x-icon name="arrow-right" /></button>
    </div>
</div>
