<div class="max-w-2xl space-y-6">
    <h1 class="display text-4xl">{{ __('admin.nav.profile') }}</h1>
    <form wire:submit="saveProfile" class="card space-y-5">
        <div>
            <label class="label">{{ __('site.auth.name') }}</label>
            <input wire:model="name" class="input">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">{{ __('site.auth.email') }}</label>
            <input type="email" wire:model="email" class="input">
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary !py-2.5">{{ __('admin.save') }}</button>
    </form>
    <form wire:submit="savePassword" class="card space-y-5">
        <h2 class="font-medium">{{ __('admin.profile.change_password') }}</h2>
        <div>
            <label class="label">{{ __('admin.profile.current_password') }}</label>
            <input type="password" wire:model="current_password" class="input" autocomplete="current-password">
            @error('current_password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="label">{{ __('site.auth.password') }}</label>
                <input type="password" wire:model="password" class="input" autocomplete="new-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ __('site.auth.password_confirmation') }}</label>
                <input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
            </div>
        </div>
        <button type="submit" class="btn-primary !py-2.5">{{ __('admin.profile.change_password') }}</button>
    </form>
</div>
