@props(['product', 'eager' => false, 'showStore' => true])
@php
    $variant = $product->defaultVariant;
    $off = $variant->compare_at_price && $variant->compare_at_price > $variant->price
        ? (int) round(100 - $variant->price * 100 / $variant->compare_at_price) : 0;
    $soldOut = $product->totalStock() < 1;
@endphp
<a href="{{ $product->url() }}" {{ $attributes->class(['group flex flex-col overflow-hidden rounded-xl border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(16,32,31,.10)]']) }}>
    <div class="relative aspect-square overflow-hidden bg-stone-100">
        @if ($product->primaryImageUrl())
            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" width="400" height="400" @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @endif
        @if ($off >= 5 && ! $soldOut)
            <span class="absolute left-2 top-2 rounded bg-deal px-2 py-0.5 text-xs font-bold text-white">{{ __(':pct% off', ['pct' => $off]) }}</span>
        @endif
        @if ($soldOut)
            <span class="absolute left-2 top-2 rounded bg-stone-800 px-2 py-0.5 text-xs font-bold text-white">{{ __('Out of stock') }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-1 p-3">
        <div class="line-clamp-2 min-h-10 text-sm font-medium leading-snug text-ink group-hover:text-brand-600">{{ $product->name }}</div>
        @if ($product->rating_count > 0)
            <x-stars :rating="$product->rating_avg" :count="$product->rating_count" size="text-xs" />
        @endif
        <div class="mt-auto flex flex-wrap items-baseline gap-x-2 pt-1">
            @if ($product->hasPriceRange()) <span class="text-xs text-muted">{{ __('From') }}</span> @endif
            <x-price :amount="$variant->price" class="text-lg font-bold text-ink" />
            @if ($off > 0)
                <s class="text-xs text-muted"><x-money :amount="$variant->compare_at_price" /></s>
            @endif
        </div>
        @if ($showStore)
            <div class="truncate text-xs text-muted">{{ __('by :store', ['store' => $product->store->name]) }}</div>
        @endif
    </div>
</a>
