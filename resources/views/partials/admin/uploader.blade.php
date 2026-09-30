{{-- Кнопка/зона загрузки. Параметры: $method, $folder, $accept, $multiple, $label --}}
<div x-data="uploader({ method: @js($method), folder: @js($folder) })"
     x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="drop($event)"
     :class="dragging ? 'border-amber bg-amber/5' : 'border-line'"
     class="rounded-xl border border-dashed p-4 transition {{ $boxClass ?? '' }}">
    <input type="file" x-ref="input" class="hidden" accept="{{ $accept ?? 'image/*' }}" @if ($multiple ?? false) multiple @endif x-on:change="handle($event.target.files)">
    <button type="button" x-on:click="pick()" class="flex w-full flex-col items-center justify-center gap-2 py-3 text-sm text-ash hover:text-bone">
        <x-icon name="upload" class="size-6 text-amber" />
        <span>{{ $uploadLabel ?? __('admin.upload.choose') }}</span>
        <span class="text-xs text-smoke">{{ __('admin.upload.drop') }}</span>
    </button>
    <template x-for="(u, i) in uploads" :key="i">
        <div class="mt-2 text-xs">
            <div class="flex justify-between gap-3"><span class="truncate" x-text="u.name"></span><span x-text="u.status === 'error' ? '✕' : u.progress + '%'"></span></div>
            <div class="mt-1 h-1 overflow-hidden rounded bg-line"><div class="h-full bg-amber transition-all" :class="u.status === 'error' && '!bg-rec'" :style="`width:${u.progress}%`"></div></div>
            <p x-show="u.error" x-text="u.error" class="mt-1 text-rec"></p>
        </div>
    </template>
</div>
