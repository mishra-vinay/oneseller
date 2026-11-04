@props(['rating', 'count' => null, 'size' => 'text-sm'])
@php $rounded = (int) round($rating); @endphp
<span {{ $attributes->class(['inline-flex items-center gap-1', $size]) }}>
    <span aria-hidden="true" class="tracking-tight text-amber-500">{{ str_repeat('★', $rounded) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $rounded) }}</span></span>
    <span class="sr-only">{{ __(':rating out of 5 stars', ['rating' => number_format($rating, 1)]) }}</span>
    @if ($count !== null)
        <span class="text-stone-500">({{ $count }})</span>
    @endif
</span>
