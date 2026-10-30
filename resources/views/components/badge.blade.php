@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', $value));
    $tone = match ($value) {
        'approved', 'delivered', 'completed', 'paid', 'active' => 'bg-emerald-100 text-emerald-800',
        'pending', 'pending_payment', 'placed' => 'bg-amber-100 text-amber-800',
        'accepted', 'shipped' => 'bg-sky-100 text-sky-800',
        'rejected', 'cancelled', 'suspended', 'failed', 'deactivated', 'disabled' => 'bg-red-100 text-red-800',
        'refunded', 'partially_refunded' => 'bg-violet-100 text-violet-800',
        default => 'bg-stone-200 text-stone-700',
    };
@endphp
<span {{ $attributes->class(['inline-block rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', $tone]) }}>{{ $label }}</span>
