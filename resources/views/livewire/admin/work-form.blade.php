@php use App\Support\Media; use App\Support\YouTube; @endphp
<form wire:submit="save" class="space-y-6" x-data="{ lang: '{{ app()->getLocale() }}' }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('admin.works') }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-ash hover:text-bone"><x-icon name="arrow-left" class="size-4" /> {{ __('admin.nav.works') }}</a>
            <h1 class="display mt-2 text-4xl">{{ $work ? __('admin.works.edit') : __('admin.works.add') }}</h1>
        </div>
        <div class="flex gap-3">
            @if ($work)
                <a href="{{ route('works.show', $work) }}" target="_blank" class="btn-ghost !py-2.5"><x-icon name="eye" class="size-4" /> {{ __('admin.preview') }}</a>
            @endif
            <button type="submit" class="btn-primary !py-2.5" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> {{ __('admin.save') }}</button>
        </div>
    </div>

    @if (session('toast'))
        <div x-init="$dispatch('toast', { text: @js(session('toast')) })"></div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <div class="card space-y-5">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-medium">{{ __('admin.works.texts') }}</h2>
                    @include('partials.admin.lang-tabs')
                </div>
                @foreach (config('app.locales') as $code => $label)
                    <div x-show="lang === '{{ $code }}'" class="space-y-5" wire:key="lang-{{ $code }}">
                        <div>
                            <label class="label">{{ __('admin.works.title') }} ({{ $label }})</label>
                            <input wire:model="title.{{ $code }}" class="input" lang="{{ $code }}">
                        </div>
                        <div>
                            <label class="label">{{ __('admin.works.excerpt') }} ({{ $label }})</label>
                            <textarea wire:model="excerpt.{{ $code }}" rows="2" class="input resize-y" lang="{{ $code }}"></textarea>
                        </div>
                        <div>
                            <label class="label">{{ __('admin.works.description') }} ({{ $label }})</label>
                            <textarea wire:model="description.{{ $code }}" rows="7" class="input resize-y" lang="{{ $code }}"></textarea>
                        </div>
                        <div>
                            <label class="label">{{ __('admin.works.role') }} ({{ $label }})</label>
                            <input wire:model="role.{{ $code }}" class="input" placeholder="{{ __('admin.works.role_hint') }}" lang="{{ $code }}">
                        </div>
                    </div>
                @endforeach
                @error('title') <p class="error">{{ $message }}</p> @enderror
                <p class="text-xs text-smoke">{{ __('admin.works.fill_hint') }}</p>
            </div>

            <div class="card space-y-4">
                <h2 class="font-medium">{{ __('admin.works.video') }}</h2>
                <input wire:model.live.debounce.500ms="video_url" class="input" placeholder="https://www.youtube.com/watch?v=…  /  https://vimeo.com/…">
                @error('video_url') <p class="error">{{ $message }}</p> @enderror
                <p class="text-xs text-smoke">{{ __('admin.works.video_hint') }}</p>
                @if ($youtubeId)
                    <div class="aspect-video overflow-hidden rounded-xl bg-black">
                        <iframe src="{{ YouTube::embed($youtubeId) }}" class="size-full" allowfullscreen></iframe>
                    </div>
                @elseif ($video_url && Media::isVideo($video_url))
                    <video src="{{ Media::url($video_url) }}" class="aspect-video w-full rounded-xl bg-black" controls preload="metadata"></video>
                @endif
                @include('partials.admin.uploader', ['method' => 'setVideo', 'folder' => 'videos', 'accept' => 'video/mp4,video/webm,video/quicktime', 'uploadLabel' => __('admin.works.upload_video')])
            </div>

            <div class="card space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">{{ __('site.works.gallery') }} <span class="text-smoke">{{ count($gallery) }}</span></h2>
                </div>
                @if ($gallery)
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($gallery as $i => $item)
                            <div class="group relative aspect-square overflow-hidden rounded-lg bg-graphite" wire:key="g-{{ $item['path'] }}">
                                @if ($item['type'] === 'video')
                                    <video src="{{ Media::url($item['path']) }}" class="size-full object-cover" muted preload="metadata"></video>
                                    <span class="absolute top-2 left-2 rounded bg-ink/70 px-1.5 text-[10px]">VIDEO</span>
                                @else
                                    <img src="{{ Media::url($item['path']) }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                                <div class="absolute inset-x-0 bottom-0 flex justify-between bg-gradient-to-t from-ink/90 p-2 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                    <div class="flex gap-1">
                                        <button type="button" wire:click="moveMedia({{ $i }}, -1)" class="grid size-7 place-items-center rounded bg-ink/80"><x-icon name="arrow-left" class="size-3.5" /></button>
                                        <button type="button" wire:click="moveMedia({{ $i }}, 1)" class="grid size-7 place-items-center rounded bg-ink/80"><x-icon name="arrow-right" class="size-3.5" /></button>
                                    </div>
                                    <div class="flex gap-1">
                                        @if ($item['type'] === 'image')
                                            <button type="button" wire:click="setCover(@js($item['path']))" title="{{ __('admin.works.make_cover') }}" class="grid size-7 place-items-center rounded bg-ink/80"><x-icon name="star" class="size-3.5" /></button>
                                        @endif
                                        <button type="button" wire:click="removeMedia({{ $i }})" class="grid size-7 place-items-center rounded bg-ink/80 text-rec"><x-icon name="trash" class="size-3.5" /></button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @include('partials.admin.uploader', ['method' => 'addMedia', 'folder' => 'gallery', 'accept' => 'image/*,video/mp4,video/webm,video/quicktime', 'multiple' => true, 'uploadLabel' => __('admin.works.upload_gallery')])
            </div>
        </div>

        <div class="space-y-6">
            <div class="card space-y-5">
                <label class="flex items-center justify-between gap-4">
                    <span>{{ __('admin.works.published') }}</span>
                    <input type="checkbox" wire:model="is_published" class="size-5 accent-amber">
                </label>
                <label class="flex items-center justify-between gap-4">
                    <span>{{ __('admin.works.featured') }}</span>
                    <input type="checkbox" wire:model="is_featured" class="size-5 accent-amber">
                </label>
            </div>

            <div class="card space-y-4">
                <h2 class="font-medium">{{ __('admin.works.cover') }}</h2>
                @php $coverPreview = $cover ? Media::url($cover) : ($youtubeId ? YouTube::thumbnail($youtubeId, 'hqdefault') : null); @endphp
                @if ($coverPreview)
                    <div class="relative aspect-video overflow-hidden rounded-xl bg-graphite">
                        <img src="{{ $coverPreview }}" alt="" class="size-full object-cover">
                        @if ($cover)
                            <button type="button" wire:click="removeCover" class="absolute top-2 right-2 grid size-8 place-items-center rounded-lg bg-ink/80 text-rec"><x-icon name="trash" class="size-4" /></button>
                        @else
                            <span class="absolute bottom-2 left-2 rounded bg-ink/80 px-2 py-0.5 text-[11px] text-ash">{{ __('admin.works.cover_auto') }}</span>
                        @endif
                    </div>
                @endif
                @include('partials.admin.uploader', ['method' => 'setCover', 'folder' => 'covers', 'accept' => 'image/*'])
            </div>

            <div class="card space-y-5">
                <div>
                    <label class="label">{{ __('site.works.category') }}</label>
                    <select wire:model="category" class="input">
                        @foreach (\App\Models\Work::CATEGORIES as $c)
                            <option value="{{ $c }}">{{ __('site.categories.'.$c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">{{ __('site.works.year') }}</label>
                    <input type="number" wire:model="year" class="input" min="1950" max="2100">
                    @error('year') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">{{ __('site.works.client') }}</label>
                    <input wire:model="client" class="input">
                </div>
            </div>
        </div>
    </div>
</form>
