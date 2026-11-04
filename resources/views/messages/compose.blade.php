<x-layouts.store :title="__('Message :store', ['store' => $store->name])">
    <x-auth-card :title="__('Message :store', ['store' => $store->name])">
        @if ($subOrder) <p class="mb-3 text-sm text-stone-600">{{ __('About parcel :number', ['number' => $subOrder->number]) }}</p> @endif
        <form method="POST" action="{{ route('messages.start', $store) }}" class="space-y-3">
            @csrf
            @if ($subOrder) <input type="hidden" name="sub_order_id" value="{{ $subOrder->id }}"> @endif
            <x-form.textarea name="body" :label="__('Your message')" rows="4" required maxlength="2000" autofocus />
            <x-form.button>{{ __('Send') }}</x-form.button>
        </form>
    </x-auth-card>
</x-layouts.store>
