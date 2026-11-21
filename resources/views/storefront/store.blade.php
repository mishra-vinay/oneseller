<x-layouts.store :bleed="true" :title="$store->name" :description="$store->description ?: __(':store, an approved seller from :city on :site.', ['store' => $store->name, 'city' => $store->city, 'site' => $siteName])"
    :canonical="route('store.show', $store)" :image="$store->logoUrl()" :noindex="filled(request('sort'))">
    @push('jsonld')
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $store->name,
            'description' => $store->description,
            'url' => route('store.show', $store),
            'image' => $store->logoUrl(),
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $store->city, 'addressRegion' => $store->state, 'postalCode' => $store->pincode, 'addressCountry' => 'IN'],
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush

    <section aria-hidden="true" class="relative h-32 overflow-hidden bg-brand-900 sm:h-48">
        <div class="absolute -top-20 right-[8%] h-80 w-80 rounded-full border-[30px] border-brand-700 opacity-70"></div>
        <div class="absolute -bottom-36 right-[24%] h-64 w-64 rounded-full border-[22px] border-saffron-500 opacity-40"></div>
    </section>

    <div class="wrap">
        <section class="panel relative -mt-14 flex flex-wrap items-center gap-5 p-5 sm:p-6">
            <x-store-avatar :store="$store" size="h-24 w-24 text-3xl" class="border-4 border-white shadow-md" />
            <div class="min-w-0 flex-1 basis-72">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-2xl font-bold sm:text-3xl">{{ $store->name }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-[13px] font-bold text-brand-600"><x-icon name="check" :size="14" stroke-width="3" /> {{ __('Approved seller') }}</span>
                </div>
                <p class="mt-1 text-sm text-muted">{{ $store->city }}, {{ $store->state }} · {{ app(\App\Services\Pricing::class)->describe($store) }}</p>
                @if ($store->description)
                    <p class="mt-2 max-w-3xl whitespace-pre-line leading-relaxed">{{ $store->description }}</p>
                @endif
            </div>
        </section>

        <div class="mb-4 mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-xl font-bold">{{ trans_choice(':count product|:count products', $products->total()) }}</h2>
            <form method="GET" class="flex items-center gap-2 text-sm" data-autosubmit>
                <label for="sort" class="text-muted">{{ __('Sort by') }}</label>
                <select id="sort" name="sort" class="field w-auto">
                    @foreach (\App\Services\Catalog::SORTS as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn-ghost">{{ __('Go') }}</button></noscript>
            </form>
        </div>
        @if ($products->isEmpty())
            <p class="panel p-10 text-center text-muted">{{ __('This store has no products on sale right now.') }}</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
                @foreach ($products as $product)
                    <x-product-card :product="$product" :show-store="false" :eager="$loop->index < 5" />
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.store>
