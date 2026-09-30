<div>
    <h1 class="display text-5xl">{{ __('site.auth.register_title') }}</h1>

    @if (! $open)
        <p class="mt-6 rounded-xl border border-line bg-coal p-5 text-ash">{{ __('site.auth.closed') }}</p>
    @else
        @if ($first)
            <p class="mt-3 text-ash">{{ __('site.auth.register_sub') }}</p>
        @endif
        <form wire:submit="register" class="mt-10 space-y-5">
            <div>
                <label class="label" for="name">{{ __('site.auth.name') }}</label>
                <input id="name" wire:model="name" class="input" autocomplete="name" required>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="email">{{ __('site.auth.email') }}</label>
                <input id="email" type="email" wire:model="email" class="input" autocomplete="email" required>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password">{{ __('site.auth.password') }}</label>
                <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">{{ __('site.auth.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn-primary w-full !py-4" wire:loading.attr="disabled">{{ __('site.auth.register') }}</button>
        </form>
    @endif

    <p class="mt-8 text-center text-sm text-ash">
        {{ __('site.auth.have_account') }}
        <a href="{{ route('login') }}" class="text-amber hover:underline">{{ __('site.auth.login') }}</a>
    </p>
</div>
