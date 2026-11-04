@php use App\Enums\ReturnStatus; use App\Enums\ReturnType; @endphp
<x-layouts.panel :title="__('Return :number', ['number' => $return->number])" area="seller">
    <div class="mb-4 flex items-center gap-3 text-sm"><x-badge :status="$return->status" /> <a href="{{ route('seller.returns.index') }}" class="text-stone-600 hover:underline">← {{ __('All returns') }}</a></div>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2"><x-return-summary :return="$return" /></x-card>
        <x-card :title="__('Next step')">
            @switch($return->status)
                @case(ReturnStatus::Requested)
                    <form method="POST" action="{{ route('seller.returns.approve', $return) }}" class="space-y-2">
                        @csrf
                        <x-form.input name="note" :label="__('Note to buyer (optional)')" :placeholder="__('Return address or instructions')" />
                        <x-form.button class="w-full">{{ __('Approve') }}</x-form.button>
                    </form>
                    <form method="POST" action="{{ route('seller.returns.reject', $return) }}" class="mt-6 space-y-2 border-t border-stone-200 pt-4">
                        @csrf
                        <label for="reject-note" class="block text-sm font-medium">{{ __('Reason for rejecting') }}</label>
                        <input id="reject-note" name="note" required class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Reject') }}</button>
                    </form>
                    @break
                @case(ReturnStatus::Approved)
                    <form method="POST" action="{{ route('seller.returns.receive', $return) }}" class="space-y-3">
                        @csrf
                        <x-form.checkbox name="restock" :label="__('Put the items back in stock')" :checked="true" />
                        <x-form.button class="w-full">{{ __('Mark item received') }}</x-form.button>
                    </form>
                    @break
                @case(ReturnStatus::Received)
                    @if ($return->type === ReturnType::Replacement)
                        <form method="POST" action="{{ route('seller.returns.replace', $return) }}" class="space-y-3">
                            @csrf
                            <x-form.input name="courier" :label="__('Courier name')" required />
                            <x-form.input name="tracking_number" :label="__('Tracking number')" required />
                            <x-form.button class="w-full">{{ __('Ship replacement') }}</x-form.button>
                        </form>
                    @else
                        <p class="text-sm">{{ __('The marketplace team will refund the buyer. The sale and commission for these items are then reversed in your earnings.') }}</p>
                    @endif
                    @break
                @default
                    <p class="text-sm text-stone-600">{{ __('Nothing left to do.') }}</p>
            @endswitch
        </x-card>
    </div>
</x-layouts.panel>
