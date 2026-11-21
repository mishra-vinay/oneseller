@php
    $category = $product->category;
    $shown = $product->defaultVariant;
    $off = $shown->compare_at_price && $shown->compare_at_price > $shown->price ? (int) round(100 - $shown->price * 100 / $shown->compare_at_price) : 0;
    $inStock = $product->totalStock() > 0;
    $isBuyerOrGuest = ! auth()->check() || auth()->user()->role === \App\Enums\Role::Buyer;
    $mainImage = $product->images->first()?->url();
    $toUrl = fn ($u) => \Illuminate\Support\Str::startsWith($u, ['http://', 'https://']) ? $u : url($u);
@endphp
<x-layouts.store :bleed="true" :title="$product->name" :description="$product->short_description ? $product->short_description.'. '.$product->description : $product->description"
    :canonical="$product->url()" :image="$mainImage ? $toUrl($mainImage) : null" og-type="product">
    @push('jsonld')
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => \Illuminate\Support\Str::limit(trim($product->short_description.' '.$product->description), 500),
            'image' => $product->images->map(fn ($i) => $toUrl($i->url()))->values()->all(),
            'sku' => $shown->sku,
            'category' => $category->fullName(),
            'brand' => ['@type' => 'Brand', 'name' => $product->store->name],
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->url(),
                'priceCurrency' => 'INR',
                'price' => number_format($shown->price / 100, 2, '.', ''),
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => $product->store->name],
            ],
            'aggregateRating' => $product->rating_count > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => round($product->rating_avg, 1), 'reviewCount' => $product->rating_count] : null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect([
                ['name' => __('Home'), 'item' => route('home')],
                $category->parent ? ['name' => $category->parent->name, 'item' => route('category.show', $category->parent)] : null,
                ['name' => $category->name, 'item' => route('category.show', $category)],
                ['name' => $product->name, 'item' => $product->url()],
            ])->filter()->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1] + $c)->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
    @if ($mainImage)
        @push('head')
            <link rel="preload" as="image" href="{{ $mainImage }}" fetchpriority="high">
        @endpush
    @endif

    <div class="wrap pt-5">
        <nav class="mb-4 text-[13px] text-muted" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="text-brand-600 hover:underline">{{ __('Home') }}</a>
            @if ($category->parent) › <a href="{{ route('category.show', $category->parent) }}" class="text-brand-600 hover:underline">{{ $category->parent->name }}</a> @endif
            › <a href="{{ route('category.show', $category) }}" class="text-brand-600 hover:underline">{{ $category->name }}</a>
            › <span aria-current="page" class="line-clamp-1 inline">{{ $product->name }}</span>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:gap-10">
            {{-- Gallery --}}
            <div class="flex flex-col-reverse gap-3 sm:flex-row lg:sticky lg:top-4 lg:self-start">
                @if ($product->images->count() > 1)
                    <div class="flex gap-2 overflow-x-auto sm:flex-col" role="group" aria-label="{{ __('Product images') }}">
                        @foreach ($product->images as $image)
                            <button type="button" data-gallery-thumb="{{ $image->url() }}" aria-label="{{ __('Show image :n', ['n' => $loop->iteration]) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                    class="h-18 w-18 shrink-0 overflow-hidden rounded-lg border-2 border-line bg-white aria-pressed:border-saffron-500">
                                <img src="{{ $image->url() }}" alt="" width="72" height="72" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="aspect-square flex-1 overflow-hidden rounded-2xl border border-line bg-white">
                    @if ($mainImage)
                        <img data-gallery-main src="{{ $mainImage }}" alt="{{ $product->name }}" width="800" height="800" fetchpriority="high" decoding="async" class="h-full w-full object-contain">
                    @endif
                </div>
            </div>

            {{-- Buy box --}}
            <div class="flex flex-col gap-4">
                <a href="{{ route('store.show', $product->store) }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('Visit the :store store', ['store' => $product->store->name]) }}</a>
                <div class="flex items-start justify-between gap-3">
                    <h1 class="font-display text-2xl font-bold leading-tight sm:text-3xl">{{ $product->name }}</h1>
                    @if ($isBuyerOrGuest)
                        <form method="POST" action="{{ route('wishlist.toggle', $product->id) }}">
                            @csrf
                            <button class="btn-ghost shrink-0 rounded-full px-3 text-sm" aria-pressed="{{ $wishlisted ? 'true' : 'false' }}">
                                <x-icon name="heart" :size="18" @class(['fill-deal text-deal' => $wishlisted]) /> {{ $wishlisted ? __('Saved') : __('Save') }}
                            </button>
                        </form>
                    @endif
                </div>
                @if ($product->short_description)
                    <p class="-mt-2 text-muted">{{ $product->short_description }}</p>
                @endif
                @if ($product->rating_count > 0)
                    <a href="#reviews" class="-mt-1 self-start hover:underline"><x-stars :rating="$product->rating_avg" :count="$product->rating_count" /></a>
                @else
                    <p class="-mt-1 text-sm text-muted">{{ __('No reviews yet') }}</p>
                @endif

                <hr class="border-line">

                <div>
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        @if ($off > 0) <span class="text-2xl font-semibold text-deal">−{{ $off }}%</span> @endif
                        @if ($product->hasPriceRange()) <span class="text-sm text-muted">{{ __('From') }}</span> @endif
                        <x-price :amount="$shown->price" class="text-4xl font-bold" />
                    </div>
                    <p class="mt-1 text-sm text-muted">
                        @if ($off > 0) {{ __('M.R.P.') }} <s><x-money :amount="$shown->compare_at_price" /></s> · @endif
                        {{ __('Inclusive of all taxes') }}
                        @if ($off > 0) <span class="sr-only">{{ __(':pct% off', ['pct' => $off]) }}</span> @endif
                    </p>
                </div>

                <div class="panel flex flex-col gap-4 p-4 sm:p-5">
                    @if ($inStock)
                        <form method="POST" action="{{ route('cart.add') }}" class="flex flex-col gap-4">
                            @csrf
                            @if ($product->hasOptions())
                                <fieldset>
                                    <legend class="mb-2 text-sm font-semibold">{{ implode(' / ', $product->option_names) }}</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($product->variants as $option)
                                            <label @class(['flex min-h-11 cursor-pointer items-center rounded-lg border px-3.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:font-semibold has-[:checked]:text-brand-600 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-600', 'border-stone-300 bg-white hover:border-stone-500' => $option->stock > 0, 'cursor-not-allowed border-dashed border-stone-300 text-stone-400' => $option->stock < 1])>
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
                                <p class="font-bold text-brand-600">{{ $variant->stock <= \App\Models\ProductVariant::LOW_STOCK_THRESHOLD ? __('Only :n left in stock', ['n' => $variant->stock]) : __('In stock') }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-3">
                                <label for="quantity" class="text-sm font-semibold">{{ __('Quantity') }}</label>
                                <div class="flex items-center overflow-hidden rounded-lg border border-stone-300" data-stepper>
                                    <button type="button" class="h-11 w-11 bg-stone-100 text-xl hover:bg-stone-200" data-step="-1" aria-label="{{ __('Decrease quantity') }}">−</button>
                                    <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ max(1, $product->variants->max('stock')) }}" value="1" class="h-11 w-14 border-0 text-center font-bold [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none">
                                    <button type="button" class="h-11 w-11 bg-stone-100 text-xl hover:bg-stone-200" data-step="1" aria-label="{{ __('Increase quantity') }}">+</button>
                                </div>
                            </div>
                            <button class="btn-primary w-full text-base">{{ __('Add to cart') }}</button>
                        </form>
                    @else
                        <p class="font-bold text-deal">{{ __('Out of stock') }}</p>
                    @endif
                    @error('quantity') <p class="text-sm text-deal">{{ $message }}</p> @enderror
                    <ul class="space-y-2 border-t border-line pt-4 text-sm">
                        <li class="flex gap-2.5"><x-icon name="truck" class="mt-0.5 shrink-0 text-brand-600" /> <span>{{ __('Shipped by :store from :city', ['store' => $product->store->name, 'city' => $product->store->city]) }} · {{ app(\App\Services\Pricing::class)->describe($product->store) }}</span></li>
                        <li class="flex gap-2.5"><x-icon name="cash" class="mt-0.5 shrink-0 text-brand-600" /> <span>{{ __('Cash on Delivery or pay online with Razorpay') }}</span></li>
                        <li class="flex gap-2.5"><x-icon name="shield" class="mt-0.5 shrink-0 text-brand-600" /> <span>{{ __('Easy returns on delivered orders') }} · <a href="{{ route('pages.refund') }}" class="text-brand-600 hover:underline">{{ __('Policy') }}</a></span></li>
                    </ul>
                </div>

                <a href="{{ route('store.show', $product->store) }}" class="panel flex items-center gap-3.5 p-4 transition hover:border-stone-400">
                    <x-store-avatar :store="$product->store" />
                    <div class="min-w-0 text-sm">
                        <div class="text-muted">{{ __('Sold and shipped by') }}</div>
                        <div class="font-display text-base font-bold text-brand-600">{{ $product->store->name }}</div>
                        <div class="text-muted">{{ $product->store->city }}{{ $product->store->state ? ', '.$product->store->state : '' }} · {{ __('Approved seller') }}</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="mt-8 grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-5">
                @if ($product->description || ! $product->hasOptions())
                    <section class="panel p-5 sm:p-6">
                        <h2 class="mb-3 font-display text-xl font-bold">{{ __('About this item') }}</h2>
                        @if ($product->description)
                            <div class="whitespace-pre-line leading-relaxed text-stone-700">{{ $product->description }}</div>
                        @endif
                        @unless ($product->hasOptions())
                            <dl class="mt-5 grid grid-cols-[8rem_1fr] overflow-hidden rounded-lg border border-line text-sm">
                                <dt class="bg-ground px-3.5 py-2.5 font-semibold">{{ __('SKU') }}</dt><dd class="px-3.5 py-2.5">{{ $variant->sku }}</dd>
                                <dt class="border-t border-line bg-ground px-3.5 py-2.5 font-semibold">{{ __('Category') }}</dt><dd class="border-t border-line px-3.5 py-2.5">{{ $category->fullName() }}</dd>
                            </dl>
                        @endunless
                    </section>
                @endif

                @if ($isBuyerOrGuest)
                    <details class="panel p-4 sm:p-5" @if ($errors->has('body')) open @endif>
                        <summary class="flex cursor-pointer items-center gap-2 font-semibold"><x-icon name="message" class="text-brand-600" /> {{ __('Ask :store a question', ['store' => $product->store->name]) }}</summary>
                        @auth
                            <form method="POST" action="{{ route('messages.start', $product->store) }}" class="mt-3 space-y-2">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <x-form.textarea name="body" id="question-body" :label="__('Your question')" rows="3" required maxlength="2000" />
                                <x-form.button>{{ __('Send') }}</x-form.button>
                            </form>
                        @else
                            <p class="mt-3 text-sm"><a href="{{ route('login') }}" class="font-semibold text-brand-600 underline">{{ __('Log in') }}</a> {{ __('to message the seller.') }}</p>
                        @endauth
                    </details>
                @endif
            </div>

            <section id="reviews" class="panel h-fit p-5 sm:p-6">
                <h2 class="mb-3 font-display text-xl font-bold">{{ __('Ratings & reviews') }}</h2>
                @if ($product->rating_count > 0)
                    <p class="mb-4 flex items-center gap-2"><x-stars :rating="$product->rating_avg" size="text-lg" /> <span class="text-sm text-muted">{{ __(':avg out of 5 · :count reviews', ['avg' => number_format($product->rating_avg, 1), 'count' => $product->rating_count]) }}</span></p>
                @endif

                @if ($canReview)
                    <form method="POST" action="{{ route('review.store', [$product->store->slug, $product->slug]) }}" class="mb-5 space-y-3 rounded-lg bg-ground p-4">
                        @csrf
                        <fieldset>
                            <legend class="mb-1 text-sm font-semibold">{{ $myReview ? __('Update your review') : __('Rate this product') }}</legend>
                            <div class="flex flex-wrap gap-3 text-sm">
                                @foreach (range(5, 1) as $stars)
                                    <label class="flex min-h-8 items-center gap-1"><input type="radio" name="rating" value="{{ $stars }}" class="accent-brand-600" @checked((int) old('rating', $myReview?->rating) === $stars) required> <span class="text-saffron-500">{{ str_repeat('★', $stars) }}</span></label>
                                @endforeach
                            </div>
                            @error('rating') <p class="mt-1 text-sm text-deal">{{ $message }}</p> @enderror
                        </fieldset>
                        <x-form.input name="title" id="review-title" :label="__('Title (optional)')" :value="$myReview?->title" maxlength="120" />
                        <x-form.textarea name="body" id="review-body" :label="__('Your review (optional)')" :value="$myReview?->body" rows="3" />
                        <x-form.button>{{ $myReview ? __('Update review') : __('Post review') }}</x-form.button>
                    </form>
                @endif

                @forelse ($reviews as $review)
                    <article class="border-b border-line py-4 first:pt-0 last:border-0">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <x-stars :rating="$review->rating" />
                            @if ($review->title) <span class="font-semibold">{{ $review->title }}</span> @endif
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ $review->authorName() }} · {{ __('Verified purchase') }} · {{ $review->created_at->format('d M Y') }}</p>
                        @if ($review->body) <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $review->body }}</p> @endif
                        @if ($review->seller_reply)
                            <div class="mt-3 rounded-md bg-ground px-3 py-2 text-sm"><span class="font-semibold">{{ __('Reply from :store', ['store' => $product->store->name]) }}:</span> {{ $review->seller_reply }}</div>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-muted">{{ __('No reviews yet. Buyers can review this product after it is delivered.') }}</p>
                @endforelse
                <div class="mt-4">{{ $reviews->fragment('reviews')->links() }}</div>
            </section>
        </div>

        @if ($moreFromStore->isNotEmpty())
            <section class="panel mt-5 p-4 sm:p-5">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="font-display text-xl font-bold">{{ __('More from :store', ['store' => $product->store->name]) }}</h2>
                    <a href="{{ route('store.show', $product->store) }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('Visit store') }}</a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                    @foreach ($moreFromStore as $other)
                        <x-product-card :product="$other" :show-store="false" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
