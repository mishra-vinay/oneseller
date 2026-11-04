<x-layouts.panel :title="__('Categories')" area="admin">
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.categories.create') }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ __('Add main category') }}</a>
    </div>
    <div class="space-y-4">
        @forelse ($categories as $category)
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        @if ($category->imageUrl())
                            <img src="{{ $category->imageUrl() }}" alt="" class="h-10 w-10 rounded object-cover">
                        @endif
                        <div>
                            <div class="font-semibold">{{ $category->name }} @unless ($category->is_active) <x-badge status="disabled" /> @endunless</div>
                            <div class="text-xs text-stone-500">/c/{{ $category->slug }} · {{ trans_choice(':count product|:count products', $category->products_count) }} · {{ __('order') }} {{ $category->sort_order }}</div>
                        </div>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('admin.categories.create', ['parent' => $category->id]) }}" class="hover:underline">{{ __('Add sub-category') }}</a>
                        <a href="{{ route('admin.categories.edit', $category) }}" class="hover:underline">{{ __('Edit') }}</a>
                        <a href="{{ route('admin.categories.delete', $category) }}" class="text-red-700 hover:underline">{{ __('Delete') }}</a>
                    </div>
                </div>
                @if ($category->children->isNotEmpty())
                    <ul class="mt-4 divide-y divide-stone-100 border-t border-stone-100 text-sm">
                        @foreach ($category->children as $child)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 pl-6">
                                <span>{{ $child->name }} <span class="text-xs text-stone-500">· {{ trans_choice(':count product|:count products', $child->products_count) }}</span> @unless ($child->is_active) <x-badge status="disabled" /> @endunless</span>
                                <span class="flex gap-3">
                                    <a href="{{ route('admin.categories.edit', $child) }}" class="hover:underline">{{ __('Edit') }}</a>
                                    <a href="{{ route('admin.categories.delete', $child) }}" class="text-red-700 hover:underline">{{ __('Delete') }}</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">{{ __('No categories yet. Sellers need at least one category to list products.') }}</p></x-card>
        @endforelse
    </div>
</x-layouts.panel>
