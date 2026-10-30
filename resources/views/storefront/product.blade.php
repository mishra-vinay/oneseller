@php $category = $product->category; @endphp
<x-layouts.store :title="$product->name" :description="$product->short_description ?: $product->description" :canonical="$product->url()">
    @push('head')
        @if ($product->primaryImageUrl())
            <meta property="og:image" content="{{ $product->primaryImageUrl() }}">
        @endif
        <meta property="og:title" content="{{ $product->name }}">
    @endpush

    <nav class="mb-4 text-sm text-stone-500" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('home') }}" class="hover:underline">{{ __('Home') }}</a>
        @if ($category->parent) › <a href="{{ route('category.show', $category->parent) }}" class="hover:underline">{{ $category->parent->name }}</a> @endif
        › <a href="{{ route('category.show', $category) }}" class="hover:underline">{{ $category->name }}</a>
    </nav>

    <div class="grid gap-8 md:grid-cols-2">
        <div>
            <div class="aspect-square overflow-hidden rounded-lg border border-stone-200 bg-white">
                @if ($product->images->isNotEmpty())
                    <img id="main-image" src="{{ $product->images->first()->url() }}" alt="{{ $product->name }}" class="h-full w-full object-contain">
                @endif
            </div>
            @if ($product->images->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2">
                    @foreach ($product->images as $image)
                        <button type="button" onclick="document.getElementById('main-image').src='{{ $image->url() }}'" class="aspect-square overflow-hidden rounded-md border border-stone-200 bg-white hover:border-stone-500">
                            <img src="{{ $image->url() }}" alt="{{ __('Image :n', ['n' => $loop->iteration]) }}" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
            <div class="mt-3 flex items-baseline gap-3">
                <x-money :amount="$variant->price" class="text-2xl font-semibold" />
                @if ($variant->compare_at_price && $variant->compare_at_price > $variant->price)
                    <s class="text-stone-500"><x-money :amount="$variant->compare_at_price" /></s>
                    <span class="text-sm font-medium text-emerald-700">{{ __(':pct% off', ['pct' => (int) round(100 - $variant->price * 100 / $variant->compare_at_price)]) }}</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-stone-500">{{ __('Inclusive of all taxes') }}</p>

            @if ($product->short_description)
                <p class="mt-4 text-stone-700">{{ $product->short_description }}</p>
            @endif

            <div class="mt-4 text-sm">
                @if ($variant->stock > 0)
                    <span class="font-medium text-emerald-700">{{ $variant->stock <= \App\Models\ProductVariant::LOW_STOCK_THRESHOLD ? __('Only :n left in stock', ['n' => $variant->stock]) : __('In stock') }}</span>
                @else
                    <span class="font-medium text-red-700">{{ __('Out of stock') }}</span>
                @endif
            </div>

            @if ($variant->stock > 0)
                <form method="POST" action="{{ route('cart.add') }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <div>
                        <label for="quantity" class="mb-1 block text-sm font-medium">{{ __('Quantity') }}</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="{{ $variant->stock }}" value="1" class="w-20 rounded-md border border-stone-300 px-3 py-2">
                    </div>
                    <x-form.button>{{ __('Add to cart') }}</x-form.button>
                </form>
                @error('quantity') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            @endif

            <a href="{{ route('store.show', $product->store) }}" class="mt-6 flex items-center gap-3 rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-stone-200">
                    @if ($product->store->logoUrl()) <img src="{{ $product->store->logoUrl() }}" alt="" class="h-full w-full object-cover"> @endif
                </div>
                <div class="text-sm">
                    <div class="text-stone-500">{{ __('Sold by') }}</div>
                    <div class="font-medium">{{ $product->store->name }} · {{ $product->store->city }}</div>
                </div>
            </a>

            <dl class="mt-6 text-sm text-stone-600">
                <div class="flex gap-2"><dt>{{ __('SKU') }}:</dt><dd>{{ $variant->sku }}</dd></div>
            </dl>
        </div>
    </div>

    @if ($product->description)
        <section class="mt-10 max-w-3xl">
            <h2 class="mb-3 text-lg font-semibold">{{ __('Description') }}</h2>
            <div class="whitespace-pre-line text-stone-700">{{ $product->description }}</div>
        </section>
    @endif

    @if ($moreFromStore->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-semibold">{{ __('More from :store', ['store' => $product->store->name]) }}</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($moreFromStore as $other)
                    <x-product-card :product="$other" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.store>
