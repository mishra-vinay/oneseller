@props(['label', 'href' => null])
<div class="rounded-lg border border-stone-200 bg-white p-4">
    <div class="text-xs uppercase tracking-wide text-stone-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold tabular-nums">
        @if ($href) <a href="{{ $href }}" class="hover:underline">{{ $slot }}</a> @else {{ $slot }} @endif
    </div>
</div>
