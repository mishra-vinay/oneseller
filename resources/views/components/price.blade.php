{{-- Storefront price: INR, plus the visitor's display currency when they chose one. Payment is always in INR. --}}
@props(['amount'])
@php $converted = \App\Support\Locale::converted((int) $amount); @endphp
@if ($converted)
    <span {{ $attributes->class(['tabular-nums whitespace-nowrap']) }}>{{ $converted }}</span>
    <span class="text-xs font-normal text-stone-500 tabular-nums whitespace-nowrap">({{ \App\Support\Money::format((int) $amount) }})</span>
@else
    <x-money :amount="$amount" {{ $attributes }} />
@endif
