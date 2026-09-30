<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm text-ash">{{ __('admin.dashboard.hello', ['name' => auth()->user()->name]) }}</p>
            <h1 class="display mt-1 text-4xl">{{ __('admin.nav.dashboard') }}</h1>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="syncYouTube" wire:loading.attr="disabled" class="btn-ghost !py-2.5">
                <x-icon name="refresh" class="size-4" wire:loading.class="animate-spin" wire:target="syncYouTube" /> {{ __('admin.youtube.sync') }}
            </button>
            <a href="{{ route('admin.works.create') }}" wire:navigate class="btn-primary !py-2.5"><x-icon name="plus" class="size-4" /> {{ __('admin.works.add') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as [$icon, $label, $value])
            <div class="card">
                <x-icon :name="$icon" class="size-5 text-amber" />
                <p class="display mt-4 text-4xl">{{ $value }}</p>
                <p class="mt-1 text-sm text-ash">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="font-medium">{{ __('admin.dashboard.recent_works') }}</h2>
                <a href="{{ route('admin.works') }}" wire:navigate class="text-sm text-amber hover:underline">{{ __('admin.all') }}</a>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @forelse ($recent as $work)
                    <a href="{{ route('admin.works.edit', $work->id) }}" wire:navigate class="group">
                        <div class="aspect-video overflow-hidden rounded-lg bg-graphite">
                            @if ($c = $work->coverUrl())<img src="{{ $c }}" alt="" class="size-full object-cover transition group-hover:scale-105" loading="lazy">@endif
                        </div>
                        <p class="mt-2 truncate text-sm">{{ $work->title }}</p>
                    </a>
                @empty
                    <p class="col-span-full py-10 text-center text-sm text-ash">{{ __('admin.works.empty') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <h2 class="font-medium">{{ __('admin.nav.messages') }}</h2>
                <a href="{{ route('admin.messages') }}" wire:navigate class="text-sm text-amber hover:underline">{{ __('admin.all') }}</a>
            </div>
            <div class="mt-4 divide-y divide-line">
                @forelse ($messages as $m)
                    <a href="{{ route('admin.messages') }}" wire:navigate class="block py-3">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            @unless ($m->read_at)<span class="size-2 rounded-full bg-rec"></span>@endunless
                            {{ $m->name }} <span class="ml-auto text-xs font-normal text-smoke">{{ $m->created_at->diffForHumans() }}</span>
                        </p>
                        <p class="mt-1 line-clamp-1 text-sm text-ash">{{ $m->body }}</p>
                    </a>
                @empty
                    <p class="py-10 text-center text-sm text-ash">{{ __('admin.messages.empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
