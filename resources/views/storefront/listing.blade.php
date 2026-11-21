@php
    $category = $category ?? null;
    $activeSub = $activeSub ?? null;
    $filtered = collect(['min', 'max', 'seller', 'in_stock', 'sort', 'q'])->contains(fn ($k) => filled(request($k)));
    $heading = $activeSub?->name ?? $title;
    $describe = $category
        ? __('Shop :name from approved independent sellers on :site. Compare prices, pay once, Cash on Delivery available.', ['name' => $heading, 'site' => $siteName])
        : null;
@endphp
<x-layouts.store :bleed="true" :title="$activeSub ? $activeSub->name.' · '.$title : $title" :description="$describe"
    :canonical="$category ? route('category.show', $category).($activeSub ? '?sub='.$activeSub->slug : '') : null"
    :noindex="! $category || $filtered">
    @if ($category)
        @push('jsonld')
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect([
                    ['name' => __('Home'), 'item' => route('home')],
                    $category->parent ? ['name' => $category->parent->name, 'item' => route('category.show', $category->parent)] : null,
                    ['name' => $category->name, 'item' => route('category.show', $category)],
                ])->filter()->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1] + $c)->all(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endpush
    @endif

    <div class="wrap pt-5">
        @if ($category)
            <nav class="mb-3 text-[13px] text-muted" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ route('home') }}" class="text-brand-600 hover:underline">{{ __('Home') }}</a>
                @if ($category->parent) › <a href="{{ route('category.show', $category->parent) }}" class="text-brand-600 hover:underline">{{ $category->parent->name }}</a> @endif
                › <span aria-current="page">{{ $category->name }}</span>
            </nav>
        @endif

        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold sm:text-3xl">{{ $heading }}</h1>
                <p class="mt-1 text-sm text-muted">{{ trans_choice(':count product|:count products', $products->total()) }}@if ($stores->count() > 0) · {{ trans_choice('from :count seller|from :count sellers', $stores->count()) }}@endif</p>
            </div>
            <form method="GET" class="flex items-center gap-2 text-sm" data-autosubmit>
                @foreach (request()->except(['sort', 'page']) as $key => $value)
                    @if (is_string($value)) <input type="hidden" name="{{ $key }}" value="{{ $value }}"> @endif
                @endforeach
                <label for="sort-top" class="text-muted">{{ __('Sort by') }}</label>
                <select id="sort-top" name="sort" class="field w-auto">
                    @foreach (\App\Services\Catalog::SORTS as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn-ghost">{{ __('Go') }}</button></noscript>
            </form>
        </div>

        @if ($category && $category->children->isNotEmpty())
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]">
                <a href="{{ request()->fullUrlWithQuery(['sub' => null, 'page' => null]) }}" class="{{ $activeSub ? 'chip' : 'chip-active' }} shrink-0">{{ __('All :name', ['name' => $category->name]) }}</a>
                @foreach ($category->children as $child)
                    <a href="{{ request()->fullUrlWithQuery(['sub' => $child->slug, 'page' => null]) }}" class="{{ $activeSub?->is($child) ? 'chip-active' : 'chip' }} shrink-0">{{ $child->name }}</a>
                @endforeach
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[16rem_1fr]">
            <aside>
                <details class="panel group lg:hidden">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 font-semibold">{{ __('Filters') }} <x-icon name="chevron" class="transition group-open:rotate-90" /></summary>
                    <div class="border-t border-line p-4"><x-catalog-filters :stores="$stores" :category="$category" :active-sub="$activeSub" id-prefix="m-" /></div>
                </details>
                <div class="panel sticky top-4 hidden p-5 lg:block">
                    <h2 class="mb-4 font-display text-lg font-bold">{{ __('Filters') }}</h2>
                    <x-catalog-filters :stores="$stores" :category="$category" :active-sub="$activeSub" />
                </div>
            </aside>
            <div>
                @if ($products->isEmpty())
                    <div class="panel p-10 text-center">
                        <p class="text-muted">{{ __('No products match. Try removing a filter.') }}</p>
                        <a href="{{ $category ? route('category.show', $category) : route('search') }}" class="btn-ghost mt-4">{{ __('Clear filters') }}</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" :eager="$loop->index < 4" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->onEachSide(1)->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.store>
