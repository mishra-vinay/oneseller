@php use App\Enums\ProductStatus; @endphp
<x-layouts.panel :title="__('Products')" area="admin">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Product name') }}" class="rounded-md border border-stone-300 px-3 py-2">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2">
            <option value="pending" @selected($statusParam === 'pending')>{{ __('Awaiting approval') }}</option>
            <option value="all" @selected($statusParam === 'all')>{{ __('All products') }}</option>
            @foreach ([ProductStatus::Approved, ProductStatus::Rejected, ProductStatus::Draft] as $case)
                <option value="{{ $case->value }}" @selected($statusParam === $case->value)>{{ $case->label() }}</option>
            @endforeach
            <option value="disabled" @selected($statusParam === 'disabled')>{{ __('Deactivated by admin') }}</option>
        </select>
        <select name="store" class="rounded-md border border-stone-300 bg-white px-3 py-2">
            <option value="">{{ __('All sellers') }}</option>
            @foreach ($stores as $store)
                <option value="{{ $store->slug }}" @selected(request('store') === $store->slug)>{{ $store->name }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
    </form>

    <form method="POST" action="{{ route('admin.products.bulk-approve') }}" id="bulk">
        @csrf
    </form>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="w-8 px-4 py-3"></th>
                    <th class="px-4 py-3">{{ __('Product') }}</th>
                    <th class="px-4 py-3">{{ __('Seller') }}</th>
                    <th class="px-4 py-3">{{ __('Price') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            @if ($product->status === ProductStatus::Pending)
                                <input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk" aria-label="{{ __('Select :name', ['name' => $product->name]) }}">
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.products.show', $product) }}" class="font-medium hover:underline">{{ $product->name }}</a>
                            <div class="text-xs text-stone-500">{{ $product->category?->name }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $product->store->name }}</td>
                        <td class="px-4 py-3"><x-money :amount="$product->defaultVariant?->price" /></td>
                        <td class="px-4 py-3">
                            <x-badge :status="$product->status" />
                            @if ($product->admin_disabled) <x-badge status="disabled" /> @endif
                        </td>
                        <td class="px-4 py-3 text-stone-600">{{ $product->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">{{ $statusParam === 'pending' ? __('Nothing waiting for approval.') : __('No products found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        @if ($products->contains(fn ($p) => $p->status === ProductStatus::Pending))
            <button form="bulk" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ __('Approve selected') }}</button>
        @endif
        {{ $products->links() }}
    </div>
</x-layouts.panel>
