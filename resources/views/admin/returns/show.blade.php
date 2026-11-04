@php use App\Enums\ReturnStatus; use App\Enums\ReturnType; @endphp
<x-layouts.panel :title="__('Return :number', ['number' => $return->number])" area="admin">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$return->status" />
        <a href="{{ route('admin.orders.show', $return->subOrder->order) }}" class="hover:underline">{{ __('Order :number', ['number' => $return->subOrder->order->number]) }}</a>
        <span class="text-stone-600">{{ $return->buyer->name }} ({{ $return->buyer->email }})</span>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2"><x-return-summary :return="$return" /></x-card>
        <x-card :title="__('Actions')">
            <div class="space-y-4">
                @if (in_array($return->status, [ReturnStatus::Requested, ReturnStatus::Rejected], true))
                    <form method="POST" action="{{ route('admin.returns.approve', $return) }}">
                        @csrf
                        <x-form.button class="w-full">{{ $return->status === ReturnStatus::Rejected ? __('Overrule and approve') : __('Approve') }}</x-form.button>
                    </form>
                @endif
                @if ($return->status === ReturnStatus::Requested)
                    <form method="POST" action="{{ route('admin.returns.reject', $return) }}" class="space-y-2">
                        @csrf
                        <x-form.input name="note" :label="__('Reason for rejecting')" required />
                        <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Reject') }}</button>
                    </form>
                @endif
                @if ($return->status === ReturnStatus::Received && $return->type === ReturnType::Refund)
                    <form method="POST" action="{{ route('admin.returns.refund', $return) }}" class="space-y-2">
                        @csrf
                        <x-form.button class="w-full">{{ __('Mark :amount refunded', ['amount' => \App\Support\Money::format($return->refund_amount)]) }}</x-form.button>
                        <p class="text-xs text-stone-500">{{ __('Refund the buyer first (Razorpay dashboard, or bank transfer for COD). This reverses the seller\'s credit and commission for these items.') }}</p>
                    </form>
                @endif
                @if (in_array($return->status, [ReturnStatus::Approved], true))
                    <p class="text-sm text-stone-600">{{ __('Waiting for the seller to receive the item.') }}</p>
                @endif
            </div>
        </x-card>
    </div>
</x-layouts.panel>
