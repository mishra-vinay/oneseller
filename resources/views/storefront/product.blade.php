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
            <div class="flex items-start justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                @if (! auth()->check() || auth()->user()->role === \App\Enums\Role::Buyer)
                    <form method="POST" action="{{ route('wishlist.toggle', $product->id) }}">
                        @csrf
                        <button class="rounded-full border border-stone-300 px-3 py-1.5 text-sm hover:border-stone-500" aria-pressed="{{ $wishlisted ? 'true' : 'false' }}">
                            <span aria-hidden="true" @class(['text-red-600' => $wishlisted])>{{ $wishlisted ? '♥' : '♡' }}</span> {{ $wishlisted ? __('Saved') : __('Save') }}
                        </button>
                    </form>
                @endif
            </div>
            @if ($product->rating_count > 0)
                <a href="#reviews" class="mt-1 inline-block hover:underline"><x-stars :rating="$product->rating_avg" :count="$product->rating_count" /></a>
            @endif
            <div class="mt-3 flex items-baseline gap-3">
                @if ($product->hasPriceRange()) <span class="text-sm text-stone-500">{{ __('From') }}</span> @endif
                <x-price :amount="$product->defaultVariant->price" class="text-2xl font-semibold" />
                @php $shown = $product->defaultVariant; @endphp
                @if ($shown->compare_at_price && $shown->compare_at_price > $shown->price)
                    <s class="text-stone-500"><x-money :amount="$shown->compare_at_price" /></s>
                    <span class="text-sm font-medium text-emerald-700">{{ __(':pct% off', ['pct' => (int) round(100 - $shown->price * 100 / $shown->compare_at_price)]) }}</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-stone-500">{{ __('Inclusive of all taxes') }}</p>

            @if ($product->short_description)
                <p class="mt-4 text-stone-700">{{ $product->short_description }}</p>
            @endif

            @php $inStock = $product->totalStock() > 0; @endphp
            @if ($inStock)
                <form method="POST" action="{{ route('cart.add') }}" class="mt-4 space-y-4">
                    @csrf
                    @if ($product->hasOptions())
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium">{{ implode(' / ', $product->option_names) }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($product->variants as $option)
                                    <label @class(['cursor-pointer rounded-md border px-3 py-2 text-sm has-[:checked]:border-stone-900 has-[:checked]:bg-stone-900 has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-stone-900 has-[:focus-visible]:ring-offset-2', 'border-stone-300 bg-white' => $option->stock > 0, 'cursor-not-allowed border-dashed border-stone-300 text-stone-400' => $option->stock < 1])>
                                        <input type="radio" name="variant_id" value="{{ $option->id }}" class="sr-only" @checked($option->is($variant)) @disabled($option->stock < 1)>
                                        {{ $option->label() }}
                                        @if ($product->hasPriceRange()) · <x-price :amount="$option->price" /> @endif
                                        @if ($option->stock < 1) · {{ __('sold out') }} @elseif ($option->stock <= \App\Models\ProductVariant::LOW_STOCK_THRESHOLD) · {{ __(':n left', ['n' => $option->stock]) }} @endif
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <p class="text-sm font-medium text-emerald-700">{{ $variant->stock <= \App\Models\ProductVariant::LOW_STOCK_THRESHOLD ? __('Only :n left in stock', ['n' => $variant->stock]) : __('In stock') }}</p>
                    @endif
                    <div class="flex items-end gap-3">
                        <div>
                            <label for="quantity" class="mb-1 block text-sm font-medium">{{ __('Quantity') }}</label>
                            <input id="quantity" name="quantity" type="number" min="1" max="{{ $product->variants->max('stock') }}" value="1" class="w-20 rounded-md border border-stone-300 px-3 py-2">
                        </div>
                        <x-form.button>{{ __('Add to cart') }}</x-form.button>
                    </div>
                </form>
            @else
                <p class="mt-4 text-sm font-medium text-red-700">{{ __('Out of stock') }}</p>
            @endif
            @error('quantity') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <a href="{{ route('store.show', $product->store) }}" class="mt-6 flex items-center gap-3 rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-stone-200">
                    @if ($product->store->logoUrl()) <img src="{{ $product->store->logoUrl() }}" alt="" class="h-full w-full object-cover"> @endif
                </div>
                <div class="text-sm">
                    <div class="text-stone-500">{{ __('Sold by') }}</div>
                    <div class="font-medium">{{ $product->store->name }} · {{ $product->store->city }}</div>
                    <div class="text-stone-500">{{ app(\App\Services\Pricing::class)->describe($product->store) }}</div>
                </div>
            </a>

            <dl class="mt-6 text-sm text-stone-600">
                @unless ($product->hasOptions())
                    <div class="flex gap-2"><dt>{{ __('SKU') }}:</dt><dd>{{ $variant->sku }}</dd></div>
                @endunless
            </dl>
        </div>
    </div>

    @if ($product->description)
        <section class="mt-10 max-w-3xl">
            <h2 class="mb-3 text-lg font-semibold">{{ __('Description') }}</h2>
            <div class="whitespace-pre-line text-stone-700">{{ $product->description }}</div>
        </section>
    @endif

    @if (! auth()->check() || auth()->user()->role === \App\Enums\Role::Buyer)
        <details class="mt-8 max-w-3xl rounded-lg border border-stone-200 bg-white p-4" @if ($errors->has('body')) open @endif>
            <summary class="cursor-pointer text-sm font-medium">{{ __('Ask :store a question', ['store' => $product->store->name]) }}</summary>
            @auth
                <form method="POST" action="{{ route('messages.start', $product->store) }}" class="mt-3 space-y-2">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <x-form.textarea name="body" :label="__('Your question')" rows="3" required maxlength="2000" />
                    <x-form.button>{{ __('Send') }}</x-form.button>
                </form>
            @else
                <p class="mt-3 text-sm"><a href="{{ route('login') }}" class="font-medium underline">{{ __('Log in') }}</a> {{ __('to message the seller.') }}</p>
            @endauth
        </details>
    @endif

    <section id="reviews" class="mt-10 max-w-3xl">
        <h2 class="mb-3 text-lg font-semibold">{{ __('Reviews') }}</h2>
        @if ($product->rating_count > 0)
            <p class="mb-4 flex items-center gap-2"><x-stars :rating="$product->rating_avg" size="text-lg" /> <span class="text-sm text-stone-600">{{ __(':avg out of 5 · :count reviews', ['avg' => number_format($product->rating_avg, 1), 'count' => $product->rating_count]) }}</span></p>
        @endif

        @if ($canReview)
            <form method="POST" action="{{ route('review.store', [$product->store->slug, $product->slug]) }}" class="mb-6 space-y-3 rounded-lg border border-stone-200 bg-white p-4">
                @csrf
                <fieldset>
                    <legend class="mb-1 text-sm font-medium">{{ $myReview ? __('Update your review') : __('Rate this product') }}</legend>
                    <div class="flex gap-3 text-sm">
                        @foreach (range(5, 1) as $stars)
                            <label class="flex items-center gap-1"><input type="radio" name="rating" value="{{ $stars }}" @checked((int) old('rating', $myReview?->rating) === $stars) required> {{ str_repeat('★', $stars) }}</label>
                        @endforeach
                    </div>
                    @error('rating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>
                <x-form.input name="title" :label="__('Title (optional)')" :value="$myReview?->title" maxlength="120" />
                <x-form.textarea name="body" :label="__('Your review (optional)')" :value="$myReview?->body" rows="3" />
                <x-form.button>{{ $myReview ? __('Update review') : __('Post review') }}</x-form.button>
            </form>
        @endif

        @forelse ($reviews as $review)
            <article class="border-b border-stone-200 py-4 last:border-0">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <x-stars :rating="$review->rating" />
                    @if ($review->title) <span class="font-medium">{{ $review->title }}</span> @endif
                </div>
                <p class="mt-1 text-xs text-stone-500">{{ $review->authorName() }} · {{ __('Verified purchase') }} · {{ $review->created_at->format('d M Y') }}</p>
                @if ($review->body) <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $review->body }}</p> @endif
                @if ($review->seller_reply)
                    <div class="mt-3 rounded-md bg-stone-100 px-3 py-2 text-sm"><span class="font-medium">{{ __('Reply from :store', ['store' => $product->store->name]) }}:</span> {{ $review->seller_reply }}</div>
                @endif
            </article>
        @empty
            <p class="text-sm text-stone-500">{{ __('No reviews yet.') }}</p>
        @endforelse
        <div class="mt-4">{{ $reviews->fragment('reviews')->links() }}</div>
    </section>

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
