<x-layouts.store :description="__(':site — shop from many independent sellers in one cart and one checkout.', ['site' => $siteName])" :canonical="route('home')">
    <section class="rounded-lg bg-stone-900 px-6 py-10 text-white sm:px-10">
        <h1 class="text-2xl font-semibold sm:text-3xl">{{ __('Many sellers. One cart. One checkout.') }}</h1>
        <p class="mt-2 max-w-xl text-stone-300">{{ __('Browse products from independent stores and pay once.') }}</p>
        <form action="{{ route('search') }}" class="mt-6 flex max-w-lg gap-2">
            <input name="q" type="search" placeholder="{{ __('What are you looking for?') }}" aria-label="{{ __('Search products') }}" class="w-full rounded-md bg-white px-3 py-2 text-stone-900 placeholder:text-stone-500">
            <button class="rounded-md bg-white px-4 py-2 font-medium text-stone-900">{{ __('Search') }}</button>
        </form>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Shop by category') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <a href="{{ route('category.show', $category) }}" class="group overflow-hidden rounded-lg border border-stone-200 bg-white hover:border-stone-400">
                        <div class="aspect-[4/3] bg-stone-100">
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="px-3 py-2 text-sm font-medium group-hover:underline">{{ $category->name }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <div class="mb-4 flex items-baseline justify-between">
            <h2 class="text-lg font-semibold">{{ __('New arrivals') }}</h2>
            <a href="{{ route('search') }}" class="text-sm hover:underline">{{ __('See all') }}</a>
        </div>
        @if ($newArrivals->isEmpty())
            <p class="rounded-lg border border-stone-200 bg-white p-6 text-sm text-stone-500">{{ __('The catalogue opens soon.') }}</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($featuredStores->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Featured sellers') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featuredStores as $store)
                    <a href="{{ route('store.show', $store) }}" class="flex items-center gap-3 rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-stone-200">
                            @if ($store->logoUrl()) <img src="{{ $store->logoUrl() }}" alt="" class="h-full w-full object-cover"> @endif
                        </div>
                        <div>
                            <div class="font-medium">{{ $store->name }}</div>
                            <div class="text-xs text-stone-500">{{ $store->city }} · {{ trans_choice(':count product|:count products', $store->visible_products_count) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.store>
