@props(['product'])
@php $variant = $product->defaultVariant; @endphp
<a href="{{ $product->url() }}" class="group block overflow-hidden rounded-lg border border-stone-200 bg-white hover:border-stone-400">
    <div class="aspect-square bg-stone-100">
        @if ($product->primaryImageUrl())
            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover">
        @endif
    </div>
    <div class="p-3">
        <div class="line-clamp-2 text-sm font-medium group-hover:underline">{{ $product->name }}</div>
        <div class="mt-0.5 truncate text-xs text-stone-500">{{ $product->store->name }}</div>
        <div class="mt-2 flex items-baseline gap-2 text-sm">
            <x-money :amount="$variant->price" class="font-semibold" />
            @if ($variant->compare_at_price && $variant->compare_at_price > $variant->price)
                <s class="text-xs text-stone-500"><x-money :amount="$variant->compare_at_price" /></s>
            @endif
        </div>
        @if ($variant->stock < 1)
            <div class="mt-1 text-xs font-medium text-red-700">{{ __('Out of stock') }}</div>
        @endif
    </div>
</a>
