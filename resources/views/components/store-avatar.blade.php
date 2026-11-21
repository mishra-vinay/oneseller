@props(['store', 'size' => 'h-12 w-12 text-base'])
@php
    $tones = ['bg-[#1e3a8a]', 'bg-[#9f1239]', 'bg-[#b45309]', 'bg-[#14532d]', 'bg-[#6d28d9]', 'bg-[#0e7490]'];
    $initials = \Illuminate\Support\Str::of($store->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
@if ($store->logoUrl())
    <img src="{{ $store->logoUrl() }}" alt="" width="96" height="96" loading="lazy" {{ $attributes->class(['shrink-0 rounded-xl object-cover', $size]) }}>
@else
    <span aria-hidden="true" {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-xl font-display font-extrabold text-white', $size, $tones[$store->id % count($tones)]]) }}>{{ $initials }}</span>
@endif
