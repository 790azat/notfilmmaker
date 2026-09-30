<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="display text-4xl">{{ __('admin.nav.works') }} <span class="text-smoke">{{ $works->count() }}</span></h1>
        <a href="{{ route('admin.works.create') }}" wire:navigate class="btn-primary !py-2.5"><x-icon name="plus" class="size-4" /> {{ __('admin.works.add') }}</a>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.search') }}" class="input sm:max-w-xs">
        <select wire:model.live="category" class="input sm:max-w-xs">
            <option value="">{{ __('site.works.all') }}</option>
            @foreach (\App\Models\Work::CATEGORIES as $c)
                <option value="{{ $c }}">{{ __('site.categories.'.$c) }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line">
        @forelse ($works as $work)
            <div wire:key="w-{{ $work->id }}" class="flex items-center gap-4 border-b border-line bg-coal p-3 last:border-b-0 sm:p-4">
                <div class="flex flex-col">
                    <button type="button" wire:click="move({{ $work->id }}, -1)" class="text-smoke hover:text-bone" aria-label="Up"><x-icon name="chevron-up" class="size-4" /></button>
                    <button type="button" wire:click="move({{ $work->id }}, 1)" class="text-smoke hover:text-bone" aria-label="Down"><x-icon name="chevron-down" class="size-4" /></button>
                </div>
                <a href="{{ route('admin.works.edit', $work->id) }}" wire:navigate class="aspect-video w-24 shrink-0 overflow-hidden rounded-lg bg-graphite sm:w-32">
                    @if ($c = $work->coverUrl())<img src="{{ $c }}" alt="" class="size-full object-cover" loading="lazy">@endif
                </a>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('admin.works.edit', $work->id) }}" wire:navigate class="block truncate font-medium hover:text-amber">{{ $work->title }}</a>
                    <p class="mt-1 text-xs text-ash">{{ $work->categoryLabel() }}@if ($work->year) · {{ $work->year }}@endif @if ($work->youtube_id) · YouTube @endif</p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="toggle({{ $work->id }}, 'is_featured')" title="{{ __('admin.works.featured') }}"
                            class="grid size-9 place-items-center rounded-lg transition {{ $work->is_featured ? 'text-amber' : 'text-smoke hover:text-bone' }}">
                        <x-icon name="star" class="size-[18px] {{ $work->is_featured ? 'fill-current' : '' }}" />
                    </button>
                    <button type="button" wire:click="toggle({{ $work->id }}, 'is_published')" title="{{ __('admin.works.published') }}"
                            class="grid size-9 place-items-center rounded-lg transition {{ $work->is_published ? 'text-bone' : 'text-smoke' }}">
                        <x-icon :name="$work->is_published ? 'eye' : 'eye-off'" class="size-[18px]" />
                    </button>
                    <a href="{{ route('admin.works.edit', $work->id) }}" wire:navigate class="grid size-9 place-items-center rounded-lg text-smoke hover:text-bone"><x-icon name="edit" class="size-[18px]" /></a>
                    <button type="button" wire:click="delete({{ $work->id }})" wire:confirm="{{ __('admin.works.confirm_delete') }}" class="grid size-9 place-items-center rounded-lg text-smoke hover:text-rec"><x-icon name="trash" class="size-[18px]" /></button>
                </div>
            </div>
        @empty
            <div class="bg-coal py-20 text-center text-ash">
                <x-icon name="film" class="mx-auto size-10" />
                <p class="mt-4">{{ __('admin.works.empty') }}</p>
            </div>
        @endforelse
    </div>
</div>
