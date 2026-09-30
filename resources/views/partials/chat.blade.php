{{-- Живой чат: сообщения уходят владельцу в Telegram, его ответы приходят сюда (resources/js/chat.js). --}}
@php
    $chatName = \Illuminate\Support\Str::before(\App\Models\Setting::text('real_name', 'notfilmmaker'), ' ');
    $chatAvatar = \App\Support\Media::url(\App\Models\Setting::get('portrait'));
@endphp
<div id="site-chat" data-send="{{ route('chat.send') }}" data-poll="{{ route('chat.poll') }}"
     data-t="{{ json_encode(['new' => __('site.chat.new'), 'error' => __('site.chat.error'), 'slow' => __('site.chat.slow')], JSON_UNESCAPED_UNICODE) }}">
    <button type="button" data-chat-toggle aria-label="{{ __('site.chat.title', ['name' => $chatName]) }}"
            class="chat-fab fixed right-4 bottom-4 z-[60] flex h-14 items-center gap-2.5 rounded-full bg-amber pr-5 pl-4 font-medium text-ink shadow-[0_12px_40px_-10px_rgba(0,0,0,.6)] transition hover:bg-bone sm:right-6 sm:bottom-6">
        <x-icon name="chat" class="size-6" />
        <span class="max-sm:hidden">{{ __('site.chat.open') }}</span>
        <i data-chat-dot class="absolute top-2 right-2 hidden size-3 rounded-full border-2 border-amber bg-rec"></i>
    </button>

    <div data-chat-box role="dialog" aria-label="{{ __('site.chat.title', ['name' => $chatName]) }}"
         class="chat-box fixed right-4 bottom-22 z-[60] h-[min(560px,calc(100dvh-120px))] w-[min(380px,calc(100vw-32px))] flex-col overflow-hidden rounded-2xl border border-line bg-coal shadow-[0_30px_80px_-20px_rgba(0,0,0,.8)] sm:right-6 sm:bottom-24">
        <div class="flex items-center gap-3 border-b border-line bg-graphite px-4 py-3">
            <div class="relative size-10 shrink-0">
                @if ($chatAvatar)
                    <img src="{{ $chatAvatar }}" alt="" class="size-10 rounded-full object-cover grayscale">
                @else
                    <span class="grid size-10 place-items-center rounded-full bg-amber font-display text-lg text-ink">{{ mb_substr($chatName, 0, 1) }}</span>
                @endif
                <i data-chat-live class="absolute right-0 bottom-0 hidden size-3 rounded-full border-2 border-graphite bg-emerald-400"></i>
            </div>
            <div class="flex-1 leading-tight">
                <p class="font-medium">{{ __('site.chat.title', ['name' => $chatName]) }}</p>
                <p class="text-xs text-ash" data-chat-sub data-online="{{ __('site.chat.online') }}">{{ __('site.chat.subtitle') }}</p>
            </div>
            <button type="button" data-chat-close class="grid size-9 place-items-center rounded-full text-ash transition hover:bg-line hover:text-bone" aria-label="{{ __('site.chat.close') }}">
                <x-icon name="x" class="size-5" />
            </button>
        </div>
        <div data-chat-list class="flex flex-1 flex-col gap-2 overflow-y-auto p-4 text-[15px] leading-snug">
            <div class="chat-msg chat-in">{{ __('site.chat.hi', ['name' => $chatName]) }}</div>
        </div>
        <form data-chat-form class="flex items-end gap-2 border-t border-line p-3">
            <textarea rows="1" maxlength="1500" placeholder="{{ __('site.chat.placeholder') }}"
                      class="max-h-32 min-h-11 flex-1 resize-none rounded-xl border border-line bg-ink px-3.5 py-2.5 text-base text-bone placeholder:text-smoke focus:border-amber focus:outline-none sm:text-[15px]"></textarea>
            <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber text-ink transition hover:bg-bone disabled:opacity-50" aria-label="{{ __('site.chat.send') }}">
                <x-icon name="send" class="size-5" />
            </button>
        </form>
    </div>
</div>
