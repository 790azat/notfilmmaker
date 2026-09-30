<div class="space-y-6">
    <h1 class="display text-4xl">{{ __('admin.nav.messages') }}</h1>
    <div class="overflow-hidden rounded-2xl border border-line">
        @forelse ($messages as $m)
            <div wire:key="m-{{ $m->id }}" class="border-b border-line bg-coal last:border-b-0">
                <button type="button" wire:click="open({{ $m->id }})" class="flex w-full items-center gap-4 p-4 text-left hover:bg-graphite sm:p-5">
                    <span class="size-2 shrink-0 rounded-full {{ $m->read_at ? 'bg-transparent' : 'bg-rec' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline gap-3">
                            <span class="truncate {{ $m->read_at ? '' : 'font-semibold' }}">{{ $m->name }}</span>
                            @if ($m->project_type)<span class="hidden rounded-full border border-line px-2 py-0.5 text-[11px] text-ash sm:inline">{{ $m->project_type }}</span>@endif
                        </span>
                        <span class="mt-1 block truncate text-sm text-ash">{{ $m->body }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-smoke">{{ $m->created_at->translatedFormat('j M, H:i') }}</span>
                </button>
                @if ($openId === $m->id)
                    <div class="space-y-4 border-t border-line p-5 sm:pl-11">
                        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <a href="mailto:{{ $m->email }}" class="text-amber hover:underline">{{ $m->email }}</a>
                            @if ($m->phone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $m->phone) }}" class="text-amber hover:underline">{{ $m->phone }}</a>@endif
                            @if ($m->locale)<span class="text-smoke">{{ strtoupper($m->locale) }}</span>@endif
                        </div>
                        <p class="whitespace-pre-line leading-relaxed">{{ $m->body }}</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: '.($m->project_type ?: \App\Models\Setting::text('name'))) }}" class="btn-primary !py-2 text-xs"><x-icon name="mail" class="size-4" /> {{ __('admin.messages.reply') }}</a>
                            <button type="button" wire:click="toggleRead({{ $m->id }})" class="btn-ghost !py-2 text-xs">{{ $m->read_at ? __('admin.messages.mark_unread') : __('admin.messages.mark_read') }}</button>
                            <button type="button" wire:click="delete({{ $m->id }})" wire:confirm="{{ __('admin.confirm') }}" class="btn !py-2 text-xs text-rec hover:bg-rec/10"><x-icon name="trash" class="size-4" /> {{ __('admin.delete') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-coal py-20 text-center text-ash">
                <x-icon name="inbox" class="mx-auto size-10" />
                <p class="mt-4">{{ __('admin.messages.empty') }}</p>
            </div>
        @endforelse
    </div>
    {{ $messages->links() }}
</div>
