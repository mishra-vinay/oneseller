<x-layouts.store :title="__('My addresses')">
    <x-account-nav />
    <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-3">
            @forelse ($addresses as $address)
                <div class="rounded-lg border border-stone-200 bg-white p-4 text-sm">
                    <div class="font-medium">{{ $address->name }} · {{ $address->phone }} @if ($address->is_default) <x-badge status="default" /> @endif</div>
                    <div class="text-stone-600">{{ $address->oneLine() }}</div>
                    <div class="mt-3 flex gap-4">
                        <a href="{{ route('account.addresses.edit', $address->id) }}" class="hover:underline">{{ __('Edit') }}</a>
                        @unless ($address->is_default)
                            <form method="POST" action="{{ route('account.addresses.default', $address->id) }}">@csrf <button class="hover:underline">{{ __('Make default') }}</button></form>
                        @endunless
                        <form method="POST" action="{{ route('account.addresses.destroy', $address->id) }}">@csrf @method('DELETE') <button class="text-red-700 hover:underline">{{ __('Remove') }}</button></form>
                    </div>
                </div>
            @empty
                <p class="rounded-lg border border-stone-200 bg-white p-6 text-sm text-stone-500">{{ __('No saved addresses yet.') }}</p>
            @endforelse
        </div>
        <x-card :title="$editing ? __('Edit address') : __('Add an address')">
            <form method="POST" action="{{ $editing ? route('account.addresses.update', $editing->id) : route('account.addresses.store') }}" class="space-y-4">
                @csrf
                @if ($editing) @method('PUT') @endif
                <x-address-fields :states="$states" :address="$editing" />
                <x-form.button>{{ $editing ? __('Save address') : __('Add address') }}</x-form.button>
            </form>
        </x-card>
    </div>
</x-layouts.store>
