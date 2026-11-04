<x-layouts.store :title="__('Return or replace')">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-xl font-semibold">{{ __('Return or replace items from :store', ['store' => $subOrder->store->name]) }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ __('Parcel :number, delivered :date.', ['number' => $subOrder->number, 'date' => $subOrder->delivered_at->format('d M Y')]) }}</p>
        <form method="POST" action="{{ route('returns.store', [$order, $subOrder->id]) }}" class="mt-6 space-y-6">
            @csrf
            <x-card :title="__('Which items?')">
                @error('items') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
                <ul class="divide-y divide-stone-100 text-sm">
                    @forelse ($items as $item)
                        <li class="flex items-center justify-between gap-4 py-2">
                            <label for="qty-{{ $item->id }}">{{ $item->displayName() }} <span class="text-stone-500">· <x-money :amount="$item->unit_price" /></span></label>
                            <select id="qty-{{ $item->id }}" name="items[{{ $item->id }}]" class="rounded-md border border-stone-300 bg-white px-2 py-1">
                                @foreach (range(0, $item->remaining_quantity) as $q)
                                    <option value="{{ $q }}" @selected((int) old("items.{$item->id}", $items->count() === 1 ? $item->remaining_quantity : 0) === $q)>{{ $q }}</option>
                                @endforeach
                            </select>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">{{ __('Everything in this parcel already has a return request.') }}</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card :title="__('What would you like?')">
                <div class="space-y-2 text-sm">
                    @foreach (\App\Enums\ReturnType::cases() as $type)
                        <label class="flex items-center gap-2"><input type="radio" name="type" value="{{ $type->value }}" @checked(old('type', 'refund') === $type->value)> {{ $type === \App\Enums\ReturnType::Refund ? __('A refund') : __('A replacement of the same item') }}</label>
                    @endforeach
                </div>
                <div class="mt-4 space-y-4">
                    <x-form.select name="reason" :label="__('Reason')" :options="collect(\App\Models\ReturnRequest::REASONS)->map(fn ($l) => __($l))->all()" :placeholder="__('Choose a reason')" required />
                    <x-form.textarea name="details" :label="__('Tell the seller more (optional)')" rows="3" />
                </div>
            </x-card>
            @if ($items->isNotEmpty())
                <x-form.button>{{ __('Send request') }}</x-form.button>
            @endif
        </form>
    </div>
</x-layouts.store>
