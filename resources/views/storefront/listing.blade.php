@php $category = $category ?? null; $activeSub = $activeSub ?? null; @endphp
<x-layouts.store :title="$activeSub ? $activeSub->name.' · '.$title : $title" :canonical="$category ? route('category.show', $category) : null">
    @if ($category)
        <nav class="mb-2 text-sm text-stone-500" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:underline">{{ __('Home') }}</a>
            @if ($category->parent) › <a href="{{ route('category.show', $category->parent) }}" class="hover:underline">{{ $category->parent->name }}</a> @endif
            › <span class="text-stone-700">{{ $category->name }}</span>
        </nav>
    @endif
    <div class="mb-6 flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-2xl font-semibold">{{ $activeSub?->name ?? $title }}</h1>
        <span class="text-sm text-stone-500">{{ trans_choice(':count product|:count products', $products->total()) }}</span>
    </div>

    <div class="grid gap-6 md:grid-cols-[14rem_1fr]">
        <aside>
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <x-catalog-filters :stores="$stores" :category="$category" :active-sub="$activeSub" />
            </div>
        </aside>
        <div>
            @if ($products->isEmpty())
                <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-500">{{ __('No products match. Try removing a filter.') }}</p>
            @else
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.store>
