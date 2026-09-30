<x-layouts.auth-page :title="__('site.nav.admin')">
    <div class="card text-center">
        <x-icon name="user" class="mx-auto size-10 text-amber" />
        <p class="mt-5 text-lg">{{ __('site.auth.pending') }}</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button class="btn-ghost">{{ __('site.nav.logout') }}</button>
        </form>
    </div>
</x-layouts.auth-page>
