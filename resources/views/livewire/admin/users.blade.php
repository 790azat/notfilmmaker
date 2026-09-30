<div class="space-y-6">
    <h1 class="display text-4xl">{{ __('admin.nav.users') }}</h1>
    <p class="max-w-2xl text-sm text-ash">{{ __('admin.users.hint') }}</p>
    <div class="overflow-hidden rounded-2xl border border-line">
        @foreach ($users as $user)
            <div wire:key="u-{{ $user->id }}" class="flex items-center gap-4 border-b border-line bg-coal p-4 last:border-b-0">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-graphite font-medium uppercase">{{ mb_substr($user->name, 0, 1) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ $user->name }} @if ($user->id === auth()->id())<span class="text-xs text-smoke">({{ __('admin.users.you') }})</span>@endif</p>
                    <p class="truncate text-sm text-ash">{{ $user->email }}</p>
                </div>
                <span class="hidden rounded-full px-3 py-1 text-xs sm:inline {{ $user->is_admin ? 'bg-amber/15 text-amber' : 'bg-graphite text-ash' }}">{{ $user->is_admin ? __('admin.users.admin') : __('admin.users.user') }}</span>
                @if ($user->id !== auth()->id())
                    <button type="button" wire:click="toggleAdmin({{ $user->id }})" class="btn-ghost !px-3 !py-1.5 text-xs">{{ $user->is_admin ? __('admin.users.revoke') : __('admin.users.grant') }}</button>
                    <button type="button" wire:click="delete({{ $user->id }})" wire:confirm="{{ __('admin.confirm') }}" class="grid size-9 place-items-center rounded-lg text-smoke hover:text-rec"><x-icon name="trash" class="size-[18px]" /></button>
                @endif
            </div>
        @endforeach
    </div>
</div>
