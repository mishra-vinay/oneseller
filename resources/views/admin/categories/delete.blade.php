<x-layouts.panel :title="__('Delete :name', ['name' => $category->name])" area="admin">
    <x-card class="max-w-xl">
        @if ($category->children_count > 0)
            <p class="text-sm">{{ __('This category has :count sub-categories. Move or delete them first.', ['count' => $category->children_count]) }}</p>
            <a href="{{ route('admin.categories.index') }}" class="mt-4 inline-block text-sm hover:underline">← {{ __('Back to categories') }}</a>
        @else
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="space-y-4">
                @csrf
                @method('DELETE')
                @if ($category->products_count > 0)
                    <p class="text-sm">{{ trans_choice('This category holds :count product. Choose a category to move it to before deleting.|This category holds :count products. Choose a category to move them to before deleting.', $category->products_count) }}</p>
                    <x-form.select name="target" :label="__('Move products to')" :placeholder="__('Choose a category')" :options="$targets->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all()" required />
                @else
                    <p class="text-sm">{{ __('This category is empty and can be deleted.') }}</p>
                @endif
                <div class="flex items-center gap-4">
                    <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">{{ __('Delete category') }}</button>
                    <a href="{{ route('admin.categories.index') }}" class="text-sm text-stone-600 hover:underline">{{ __('Cancel') }}</a>
                </div>
            </form>
        @endif
    </x-card>
</x-layouts.panel>
