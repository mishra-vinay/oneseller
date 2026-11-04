<x-layouts.panel :title="__('Products')" area="seller">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap gap-2 text-sm">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search my products') }}" class="rounded-md border border-stone-300 px-3 py-2">
            <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\ProductStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
        </form>
        <a href="{{ route('seller.products.create') }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ __('Add product') }}</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Price') }}</th>
                    <th class="px-4 py-3">{{ __('Stock') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded bg-stone-100">
                                    @if ($product->primaryImageUrl())
                                        <img src="{{ $product->primaryImageUrl() }}" alt="" class="h-full w-full object-cover">
                                    @endif
                                </div>
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-xs text-stone-500">{{ $product->defaultVariant?->sku }} · {{ $product->category?->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3"><x-money :amount="$product->defaultVariant?->price" /></td>
                        <td @class(['px-4 py-3 tabular-nums', 'font-medium text-red-700' => $product->defaultVariant?->isLowStock()])>{{ $product->defaultVariant?->stock }}</td>
                        <td class="px-4 py-3">
                            <x-badge :status="$product->status" />
                            @unless ($product->is_published) <x-badge status="unpublished" /> @endunless
                            @if ($product->admin_disabled) <x-badge status="disabled" /> @endif
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('seller.products.edit', $product->id) }}" class="font-medium hover:underline">{{ __('Edit') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">{{ __('No products yet.') }} <a href="{{ route('seller.products.create') }}" class="font-medium text-stone-900 hover:underline">{{ __('Add your first product') }}</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.panel>
