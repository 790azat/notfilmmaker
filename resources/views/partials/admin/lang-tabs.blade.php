<div class="flex gap-1 rounded-full border border-line p-1">
    @foreach (config('app.locales') as $code => $label)
        <button type="button" x-on:click="lang = '{{ $code }}'" class="tab !py-1" :class="lang === '{{ $code }}' ? 'tab-active' : 'tab-idle'">{{ $label }}</button>
    @endforeach
</div>
