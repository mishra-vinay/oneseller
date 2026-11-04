<x-layouts.panel :title="$category->exists ? __('Edit category') : __('New category')" area="admin">
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data" class="max-w-xl space-y-6">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <x-card>
            <div class="space-y-4">
                <x-form.input name="name" :label="__('Name')" :value="$category->name" required />
                <x-form.input name="slug" :label="__('Web address')" :value="$category->slug" :hint="__('Used in /c/… links. Leave empty to create it from the name.')" />
                <x-form.select name="parent_id" :label="__('Parent')" :value="$category->parent_id" :placeholder="__('None (main category)')" :options="$parents->pluck('name', 'id')->all()" />
                <x-form.input name="sort_order" type="number" min="0" :label="__('Display order')" :value="$category->sort_order ?? 0" :hint="__('Lower numbers appear first.')" />
                <div>
                    <span class="mb-1 block text-sm font-medium">{{ __('Image') }}</span>
                    @if ($category->imageUrl()) <img src="{{ $category->imageUrl() }}" alt="" class="mb-2 h-16 w-16 rounded object-cover"> @endif
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-form.checkbox name="is_active" :label="__('Active')" :checked="$category->is_active" :hint="__('Inactive categories and their products are hidden from the storefront.')" />
            </div>
        </x-card>
        <div class="flex items-center gap-4">
            <x-form.button>{{ __('Save category') }}</x-form.button>
            <a href="{{ route('admin.categories.index') }}" class="text-sm text-stone-600 hover:underline">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.panel>
