@php use App\Models\Setting; @endphp
<div>
    <section class="pt-40 pb-24 sm:pt-48">
        <div class="container-x grid gap-16 lg:grid-cols-[1fr_1.1fr] lg:gap-24">
            <div>
                <p class="kicker reveal">{{ __('site.contact.title') }}</p>
                <h1 class="display reveal mt-6 text-6xl sm:text-8xl">{{ __('site.home.cta_title') }}</h1>
                <p class="reveal mt-8 max-w-md text-lg text-ash">{{ __('site.contact.subtitle') }}</p>

                <div class="reveal mt-12 space-y-5">
                    <p class="label">{{ __('site.contact.or_write') }}</p>
                    @if ($email = Setting::get('email'))
                        <a href="mailto:{{ $email }}" class="flex items-center gap-4 text-xl transition hover:text-amber"><x-icon name="mail" class="size-6 shrink-0 text-amber" /> {{ $email }}</a>
                    @endif
                    @if ($phone = Setting::get('phone'))
                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}" class="flex items-center gap-4 text-xl transition hover:text-amber"><x-icon name="phone" class="size-6 shrink-0 text-amber" /> {{ $phone }}</a>
                    @endif
                    <p class="flex items-center gap-4 text-xl"><x-icon name="pin" class="size-6 shrink-0 text-amber" /> {{ Setting::text('location', 'Yerevan, Armenia') }}</p>
                    <div class="pt-4">@include('partials.socials')</div>
                </div>
            </div>

            <div class="reveal">
                @if ($sent)
                    <div class="card flex min-h-[420px] flex-col items-center justify-center text-center">
                        <span class="grid size-16 place-items-center rounded-full bg-amber text-ink"><x-icon name="check" class="size-7" /></span>
                        <p class="mt-6 max-w-sm text-lg">{{ __('site.contact.sent') }}</p>
                    </div>
                @else
                    <form wire:submit="send" class="card space-y-5 !p-6 sm:!p-10">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="c-name">{{ __('site.contact.name') }}</label>
                                <input id="c-name" wire:model="name" class="input" autocomplete="name" required>
                                @error('name') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="c-email">{{ __('site.contact.email') }}</label>
                                <input id="c-email" type="email" wire:model="email" class="input" autocomplete="email" required>
                                @error('email') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="c-phone">{{ __('site.contact.phone') }}</label>
                                <input id="c-phone" type="tel" wire:model="phone" class="input" autocomplete="tel">
                            </div>
                            <div>
                                <label class="label" for="c-type">{{ __('site.contact.project_type') }}</label>
                                <select id="c-type" wire:model="project_type" class="input">
                                    <option value="">—</option>
                                    @foreach (['music_video', 'short', 'film', 'series', 'commercial', 'documentary', 'photo'] as $t)
                                        <option value="{{ __('site.categories.'.$t) }}">{{ __('site.categories.'.$t) }}</option>
                                    @endforeach
                                    <option value="{{ __('site.contact.other') }}">{{ __('site.contact.other') }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label" for="c-body">{{ __('site.contact.message') }}</label>
                            <textarea id="c-body" wire:model="body" rows="6" class="input resize-none" required></textarea>
                            @error('body') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <input type="text" wire:model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <button type="submit" class="btn-primary w-full !py-4" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="send">{{ __('site.contact.send') }}</span>
                            <span wire:loading wire:target="send">{{ __('site.contact.sending') }}</span>
                            <x-icon name="arrow-up-right" class="size-4" />
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>
</div>
