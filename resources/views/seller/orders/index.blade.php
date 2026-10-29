@php use App\Enums\SubOrderStatus; @endphp
<x-layouts.panel :title="__('Orders')" area="seller">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Order number') }}" class="rounded-md border border-stone-300 px-3 py-2">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (array_filter(SubOrderStatus::cases(), fn ($c) => $c !== SubOrderStatus::PendingPayment) as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
    </form>
    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Order') }}</th>
                    <th class="px-4 py-3">{{ __('Items') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Subtotal') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Commission') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net payout') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($subOrders as $subOrder)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('seller.orders.show', $subOrder->id) }}" class="font-medium hover:underline">{{ $subOrder->number }}</a>
                            <div class="text-xs text-stone-500">{{ $subOrder->created_at->format('d M, g:i A') }} · {{ $subOrder->order->payment_method->label() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @foreach ($subOrder->items as $item)
                                <div>{{ $item->quantity }} × {{ $item->product_name }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$subOrder->items_subtotal" /></td>
                        <td class="px-4 py-3 text-right text-stone-600">−<x-money :amount="$subOrder->commission_amount" /></td>
                        <td class="px-4 py-3 text-right font-medium"><x-money :amount="$subOrder->net_payout" /></td>
                        <td class="px-4 py-3"><x-badge :status="$subOrder->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">{{ __('No orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subOrders->links() }}</div>
</x-layouts.panel>
