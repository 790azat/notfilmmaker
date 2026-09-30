<div>
    <section class="pt-40 pb-12 sm:pt-48">
        <div class="container-x">
            <p class="kicker reveal">{{ __('site.nav.works') }}</p>
            <h1 class="display reveal mt-6 text-6xl sm:text-8xl lg:text-9xl">{{ __('site.works.title') }}</h1>
            <p class="reveal mt-6 max-w-xl text-lg text-ash">{{ __('site.works.subtitle') }}</p>
        </div>
    </section>

    <div class="sticky top-20 z-40 border-y border-line bg-ink/85 backdrop-blur-xl">
        <div class="container-x flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="-mx-5 flex gap-2 overflow-x-auto px-5 [scrollbar-width:none] sm:mx-0 sm:px-0">
                <button type="button" wire:click="setCategory('')" class="tab shrink-0 {{ $category === '' ? 'tab-active' : 'tab-idle border border-line' }}">
                    {{ __('site.works.all') }} <span class="ml-1 opacity-50">{{ $counts->sum() }}</span>
                </button>
                @foreach ($categories as $cat)
                    <button type="button" wire:click="setCategory('{{ $cat }}')" class="tab shrink-0 {{ $category === $cat ? 'tab-active' : 'tab-idle border border-line' }}">
                        {{ __('site.categories.'.$cat) }} <span class="ml-1 opacity-50">{{ $counts[$cat] }}</span>
                    </button>
                @endforeach
            </div>
            <label class="relative block lg:w-72">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-smoke" />
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('site.works.search') }}" class="input !rounded-full !py-2.5 !pl-11">
            </label>
        </div>
    </div>

    <section class="py-14 sm:py-20">
        <div class="container-x">
            <div wire:loading.class="opacity-40" class="grid gap-5 transition sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                @forelse ($works as $work)
                    @include('partials.work-card', ['work' => $work])
                @empty
                    <div class="col-span-full grid place-items-center rounded-2xl border border-dashed border-line py-24 text-ash">
                        <x-icon name="film" class="size-10" />
                        <p class="mt-4">{{ __('site.works.empty') }}</p>
                    </div>
                @endforelse
            </div>

            @if ($works->count() < $total)
                <div class="mt-14 flex justify-center">
                    <button type="button" wire:click="loadMore" class="btn-ghost" wire:loading.attr="disabled">
                        {{ __('site.works.load_more') }}
                        <span class="text-smoke">{{ $works->count() }} / {{ $total }}</span>
                    </button>
                </div>
            @endif
        </div>
    </section>
</div>
