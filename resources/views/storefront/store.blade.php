<x-layouts.store :title="$store->name" :description="$store->description" :canonical="route('store.show', $store)">
    <section class="mb-8 flex flex-wrap items-center gap-4 rounded-lg border border-stone-200 bg-white p-6">
        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full bg-stone-200">
            @if ($store->logoUrl()) <img src="{{ $store->logoUrl() }}" alt="" class="h-full w-full object-cover"> @endif
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-semibold">{{ $store->name }}</h1>
            <p class="text-sm text-stone-500">{{ $store->city }}, {{ $store->state }}</p>
            @if ($store->description)
                <p class="mt-2 max-w-2xl whitespace-pre-line text-sm text-stone-700">{{ $store->description }}</p>
            @endif
        </div>
    </section>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ trans_choice(':count product|:count products', $products->total()) }}</h2>
        <form method="GET" class="flex items-center gap-2 text-sm">
            <label for="sort">{{ __('Sort by') }}</label>
            <select id="sort" name="sort" class="rounded-md border border-stone-300 bg-white px-2 py-1.5" onchange="this.form.submit()">
                @foreach (\App\Services\Catalog::SORTS as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <noscript><button class="rounded-md border px-2 py-1.5">{{ __('Go') }}</button></noscript>
        </form>
    </div>
    @if ($products->isEmpty())
        <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-500">{{ __('This store has no products on sale right now.') }}</p>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.store>
