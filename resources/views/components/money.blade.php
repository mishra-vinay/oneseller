@props(['amount'])
<span {{ $attributes->class(['tabular-nums whitespace-nowrap']) }}>{{ \App\Support\Money::format((int) $amount) }}</span>
