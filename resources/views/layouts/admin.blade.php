<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-ink">
    @php
        $unread = \App\Models\Message::whereNull('read_at')->count();
        $nav = [
            'admin.dashboard' => ['home', __('admin.nav.dashboard')],
            'admin.works' => ['film', __('admin.nav.works')],
            'admin.messages' => ['inbox', __('admin.nav.messages')],
            'admin.settings' => ['settings', __('admin.nav.settings')],
            'admin.users' => ['users', __('admin.nav.users')],
            'admin.profile' => ['user', __('admin.nav.profile')],
        ];
    @endphp
    <div class="lg:grid lg:min-h-screen lg:grid-cols-[260px_1fr]">
        <aside class="hidden border-r border-line bg-coal lg:flex lg:flex-col">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-3 px-6 py-7">
                <span class="grid size-9 place-items-center rounded-full border border-bone/30"><span class="size-2 rounded-full bg-rec animate-rec"></span></span>
                <span>
                    <span class="display block text-lg leading-none">{{ \App\Models\Setting::text('name', 'Portfolio') }}</span>
                    <span class="text-[11px] tracking-wider text-smoke uppercase">{{ __('admin.panel') }}</span>
                </span>
            </a>
            <nav class="flex flex-1 flex-col gap-1 px-3">
                @foreach ($nav as $route => [$icon, $label])
                    <a href="{{ route($route) }}" wire:navigate
                       class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm transition {{ request()->routeIs($route.'*') ? 'bg-bone text-ink' : 'text-ash hover:bg-graphite hover:text-bone' }}">
                        <x-icon :name="$icon" class="size-[18px]" />
                        <span class="flex-1">{{ $label }}</span>
                        @if ($route === 'admin.messages' && $unread)
                            <span class="rounded-full bg-rec px-2 py-0.5 text-[11px] font-medium text-white">{{ $unread }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <div class="space-y-3 border-t border-line p-4">
                <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm text-ash hover:bg-graphite hover:text-bone">
                    <x-icon name="globe" class="size-[18px]" /> {{ __('admin.view_site') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm text-ash hover:bg-graphite hover:text-bone">
                        <x-icon name="logout" class="size-[18px]" /> {{ __('site.nav.logout') }}
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-line bg-ink/85 backdrop-blur-xl">
                <div class="flex h-16 items-center justify-between gap-4 px-5 sm:px-8">
                    <p class="truncate font-medium">{{ $title ?? __('admin.panel') }}</p>
                    <div class="flex items-center gap-3">
                        @include('partials.lang-switch')
                        <a href="{{ route('home') }}" target="_blank" class="grid size-9 place-items-center rounded-full border border-line text-ash hover:text-bone lg:hidden" aria-label="{{ __('admin.view_site') }}"><x-icon name="globe" class="size-4" /></a>
                    </div>
                </div>
                <nav class="flex gap-1 overflow-x-auto border-t border-line px-3 py-2 [scrollbar-width:none] lg:hidden">
                    @foreach ($nav as $route => [$icon, $label])
                        <a href="{{ route($route) }}" wire:navigate class="flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-sm {{ request()->routeIs($route.'*') ? 'bg-bone text-ink' : 'text-ash' }}">
                            <x-icon :name="$icon" class="size-4" /> {{ $label }}
                            @if ($route === 'admin.messages' && $unread)<span class="rounded-full bg-rec px-1.5 text-[10px] text-white">{{ $unread }}</span>@endif
                        </a>
                    @endforeach
                </nav>
            </header>

            <main class="px-5 py-8 sm:px-8 lg:py-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div x-data="{ show: false, text: '', type: 'ok' }"
         x-on:toast.window="text = $event.detail.text ?? $event.detail[0]?.text; type = $event.detail.type ?? $event.detail[0]?.type ?? 'ok'; show = true; clearTimeout(window.__t); window.__t = setTimeout(() => show = false, 3500)"
         x-cloak x-show="show" x-transition
         class="fixed right-5 bottom-5 z-[90] flex max-w-sm items-center gap-3 rounded-xl border px-5 py-4 text-sm shadow-2xl"
         :class="type === 'error' ? 'border-rec/40 bg-[#2a1214] text-bone' : 'border-line bg-graphite text-bone'">
        <x-icon name="check" class="size-5 text-amber" x-show="type !== 'error'" />
        <span x-text="text"></span>
    </div>
</body>
</html>
