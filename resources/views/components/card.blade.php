@props(['title' => null])
<section {{ $attributes->class(['rounded-lg border border-stone-200 bg-white p-5']) }}>
    @if ($title)
        <h2 class="mb-4 font-semibold">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
