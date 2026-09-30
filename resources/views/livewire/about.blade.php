@php use App\Models\Setting; use App\Support\Media; @endphp
<div>
    <section class="pt-40 pb-16 sm:pt-48">
        <div class="container-x grid gap-14 lg:grid-cols-[1.1fr_1fr] lg:gap-20">
            <div>
                <p class="kicker reveal">{{ __('site.about.title') }}</p>
                <h1 class="display reveal mt-6 text-6xl sm:text-8xl">{{ Setting::text('real_name', 'Hrach Harutyunyan') }}</h1>
                <p class="reveal mt-3 font-mono text-sm tracking-widest text-amber">@notfilmmaker</p>
                <p class="reveal mt-8 text-2xl text-bone/90">{{ Setting::text('hero_title', 'Director · Cinematographer · Editor') }}</p>
                <div class="prose-film reveal mt-10">
                    {!! nl2br(e(Setting::text('about_text', ''))) !!}
                </div>
                <div class="reveal mt-12">
                    <p class="label">{{ __('site.about.follow') }}</p>
                    @include('partials.socials')
                </div>
            </div>
            <div class="reveal lg:pt-10">
                @if ($portrait = Setting::get('portrait'))
                    <img src="{{ Media::url($portrait) }}" alt="{{ Setting::text('name') }}" class="aspect-[4/5] w-full rounded-2xl object-cover">
                @else
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($photos->take(4) as $w)
                            @if ($c = $w->coverUrl())
                                <img src="{{ $c }}" alt="" class="aspect-[3/4] w-full rounded-xl object-cover {{ $loop->even ? 'mt-10' : '' }}" loading="lazy">
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="border-t border-line py-24">
        <div class="container-x">
            <p class="kicker reveal">{{ __('site.about.skills') }}</p>
            <div class="mt-10 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('site.services') as $service)
                    <div class="reveal flex items-start gap-5 bg-ink p-8">
                        <x-icon :name="$service['icon']" class="size-7 shrink-0 text-amber" />
                        <div>
                            <h3 class="display text-2xl">{{ $service['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ash">{{ $service['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-24 text-center">
        <div class="container-x">
            <h2 class="display reveal mx-auto max-w-4xl text-5xl sm:text-7xl">{{ __('site.home.cta_title') }}</h2>
            <a href="{{ route('contact') }}" wire:navigate class="btn-primary reveal mt-10">{{ __('site.hero.cta_contact') }} <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </section>
</div>
