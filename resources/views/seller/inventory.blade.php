@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Inventory')" area="seller">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap gap-2 text-sm">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Name or SKU') }}" class="rounded-md border border-stone-300 px-3 py-2">
            <label class="flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2"><input type="checkbox" name="low" value="1" @checked(request()->boolean('low')) onchange="this.form.submit()"> {{ __('Low stock only') }}</label>
            <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
        </form>
        <p class="text-xs text-stone-500">{{ __('Price changes on live products go for approval; stock changes are instant.') }}</p>
    </div>

    <form method="POST" action="{{ route('seller.inventory.update') }}">
        @csrf
        @method('PUT')
        <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Product') }}</th>
                        <th class="px-4 py-3">{{ __('SKU') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="w-36 px-4 py-3">{{ __('Price (₹)') }}</th>
                        <th class="w-28 px-4 py-3">{{ __('Stock') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($products as $product)
                        @php $variant = $product->defaultVariant; @endphp
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('seller.products.edit', $product->id) }}" class="hover:underline">{{ $product->name }}</a></td>
                            <td class="px-4 py-2 text-stone-600">{{ $variant->sku }}</td>
                            <td class="px-4 py-2"><x-badge :status="$product->status" /></td>
                            <td class="px-4 py-2">
                                <input name="rows[{{ $product->id }}][price]" value="{{ old("rows.{$product->id}.price", Money::toRupees($variant->price)) }}" inputmode="decimal" aria-label="{{ __('Price for :name', ['name' => $product->name]) }}"
                                       @class(['w-full rounded-md border px-2 py-1 tabular-nums', 'border-red-400' => $errors->has("rows.{$product->id}.price"), 'border-stone-300' => ! $errors->has("rows.{$product->id}.price")])>
                            </td>
                            <td class="px-4 py-2">
                                <input name="rows[{{ $product->id }}][stock]" type="number" min="0" value="{{ old("rows.{$product->id}.stock", $variant->stock) }}" aria-label="{{ __('Stock for :name', ['name' => $product->name]) }}"
                                       @class(['w-full rounded-md border px-2 py-1 tabular-nums', 'border-red-400' => $errors->has("rows.{$product->id}.stock"), 'border-amber-400 bg-amber-50' => $variant->isLowStock(), 'border-stone-300' => ! $variant->isLowStock()])>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">{{ __('No products to show.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <x-form.button>{{ __('Save all changes') }}</x-form.button>
                {{ $products->links() }}
            </div>
        @endif
    </form>
</x-layouts.panel>
