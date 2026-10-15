<div {{ $attributes ?? '' }} class="flex items-center gap-1 text-xs font-medium tracking-wider {{ $class ?? '' }}">
    @foreach (config('app.locales') as $code => $label)
        <a href="{{ \App\Support\Seo::localized($code, absolute: false) }}"
           class="rounded-full px-2.5 py-1 transition {{ app()->getLocale() === $code ? 'bg-bone text-ink' : 'text-ash hover:text-bone' }}"
           hreflang="{{ $code }}">{{ $label }}</a>
    @endforeach
</div>
