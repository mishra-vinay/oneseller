@php use App\Enums\ProductStatus; $variant = $product->defaultVariant; @endphp
<x-layouts.panel :title="$product->name" area="admin">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$product->status" />
        @if ($product->admin_disabled) <x-badge status="disabled" /> @endif
        <a href="{{ route('admin.products.index') }}" class="text-stone-600 hover:underline">← {{ __('Approval queue') }}</a>
        <a href="{{ route('admin.sellers.show', $product->store) }}" class="text-stone-600 hover:underline">{{ $product->store->name }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                @if ($product->images->isNotEmpty())
                    <div class="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                        @foreach ($product->images as $image)
                            <a href="{{ $image->url() }}" target="_blank"><img src="{{ $image->url() }}" alt="" class="aspect-square w-full rounded-md border border-stone-200 object-cover"></a>
                        @endforeach
                    </div>
                @else
                    <p class="mb-4 text-sm text-amber-800">{{ __('No images uploaded.') }}</p>
                @endif
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-stone-500">{{ __('Category') }}</dt><dd>{{ $product->category?->fullName() }}</dd></div>
                    <div><dt class="text-stone-500">{{ __('SKU') }}</dt><dd>{{ $variant?->sku }}</dd></div>
                    <div><dt class="text-stone-500">{{ __('Price') }}</dt><dd><x-money :amount="$variant?->price" /> @if ($variant?->compare_at_price) <s class="text-stone-500"><x-money :amount="$variant->compare_at_price" /></s> @endif</dd></div>
                    <div><dt class="text-stone-500">{{ __('Stock') }}</dt><dd>{{ $variant?->stock }}</dd></div>
                </dl>
            </x-card>
            <x-card :title="__('Description')">
                @if ($product->short_description) <p class="mb-3 text-sm font-medium">{{ $product->short_description }}</p> @endif
                <div class="whitespace-pre-line text-sm text-stone-700">{{ $product->description ?: __('No description.') }}</div>
            </x-card>
        </div>

        <x-card :title="__('Review')">
            <div class="space-y-4">
                @if ($product->status !== ProductStatus::Approved)
                    <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                        @csrf
                        <x-form.button class="w-full">{{ __('Approve') }}</x-form.button>
                    </form>
                @endif
                @if (in_array($product->status, [ProductStatus::Pending, ProductStatus::Approved], true))
                    <form method="POST" action="{{ route('admin.products.reject', $product) }}" class="space-y-2">
                        @csrf
                        <x-form.input name="reason" :label="__('Rejection reason (sent to the seller)')" required />
                        <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Reject') }}</button>
                    </form>
                @endif
                @if ($product->rejection_reason)
                    <p class="text-sm"><span class="text-stone-500">{{ __('Last rejection:') }}</span> {{ $product->rejection_reason }}</p>
                @endif
                <form method="POST" action="{{ $product->admin_disabled ? route('admin.products.enable', $product) : route('admin.products.disable', $product) }}">
                    @csrf
                    <button class="w-full rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium hover:bg-stone-50">
                        {{ $product->admin_disabled ? __('Reactivate product') : __('Force-deactivate product') }}
                    </button>
                </form>
            </div>
        </x-card>
    </div>
</x-layouts.panel>
