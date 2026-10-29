@props(['current'])
@php $steps = [1 => __('Address'), 2 => __('Review'), 3 => __('Payment')]; @endphp
<ol class="mb-6 flex gap-2 text-sm">
    @foreach ($steps as $n => $label)
        <li @class(['flex items-center gap-2 rounded-full px-3 py-1', 'bg-stone-900 text-white' => $n === $current, 'bg-stone-200 text-stone-600' => $n !== $current])>
            <span class="font-semibold">{{ $n }}</span> {{ $label }}
        </li>
    @endforeach
</ol>
