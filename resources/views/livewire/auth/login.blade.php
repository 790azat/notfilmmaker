<div>
    <h1 class="display text-5xl">{{ __('site.auth.login_title') }}</h1>
    <p class="mt-3 text-ash">{{ __('site.auth.login_sub') }}</p>

    <form wire:submit="login" class="mt-10 space-y-5">
        <div>
            <label class="label" for="email">{{ __('site.auth.email') }}</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email" autofocus required>
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password">{{ __('site.auth.password') }}</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-3 text-sm text-ash">
            <input type="checkbox" wire:model="remember" class="size-4 rounded border-line bg-coal accent-amber">
            {{ __('site.auth.remember') }}
        </label>
        <button type="submit" class="btn-primary w-full !py-4" wire:loading.attr="disabled">{{ __('site.auth.login') }}</button>
    </form>

    @if ($canRegister)
        <p class="mt-8 text-center text-sm text-ash">
            {{ __('site.auth.no_account') }}
            <a href="{{ route('register') }}" class="text-amber hover:underline">{{ __('site.auth.register') }}</a>
        </p>
    @endif
</div>
