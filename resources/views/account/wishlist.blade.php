<x-layouts.store :title="__('My wishlist')">
    <x-account-nav />
    @if ($products->isEmpty())
        <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-600">{{ __('Nothing saved yet. Tap “Save” on a product to keep it here.') }}</p>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <div>
                    @if ($visibleIds->has($product->id))
                        <x-product-card :product="$product" />
                    @else
                        <div class="rounded-lg border border-dashed border-stone-300 bg-white p-4 text-sm text-stone-500">{{ $product->name }} · {{ __('no longer available') }}</div>
                    @endif
                    <form method="POST" action="{{ route('wishlist.toggle', $product->id) }}" class="mt-1">
                        @csrf
                        <button class="text-xs text-stone-600 hover:underline">{{ __('Remove') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.store>
