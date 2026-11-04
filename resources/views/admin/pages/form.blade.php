<x-layouts.panel :title="$page->exists ? __('Edit: :title', ['title' => $page->title]) : ($page->type === 'post' ? __('New blog post') : __('New page'))" area="admin">
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($page->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $page->type }}">
        <x-card class="space-y-4 lg:col-span-2">
            <x-form.input name="title" :label="__('Title')" :value="$page->title" required />
            <x-form.textarea name="body" :label="__('Content (Markdown)')" :value="$page->body" rows="18" required
                :hint="__('Use # for headings, **bold**, *italic*, - for lists and [text](https://link) for links. HTML is not allowed.')" />
        </x-card>
        <div class="space-y-6">
            <x-card class="space-y-4">
                <x-form.input name="slug" :label="__('Web address')" :value="$page->slug" :hint="$page->type === 'post' ? '/blog/…' : '/p/…'" />
                <x-form.textarea name="excerpt" :label="__('Summary (optional)')" :value="$page->excerpt" rows="3" maxlength="300" />
                @if ($page->type === 'post')
                    <div>
                        <span class="mb-1 block text-sm font-medium">{{ __('Cover image') }}</span>
                        @if ($page->coverUrl()) <img src="{{ $page->coverUrl() }}" alt="" class="mb-2 w-full rounded"> @endif
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    </div>
                @endif
                <x-form.input name="published_at" type="datetime-local" :label="__('Publish date (optional)')" :value="$page->published_at?->format('Y-m-d\TH:i')" />
                <x-form.checkbox name="is_published" :label="__('Published')" :checked="$page->is_published" />
                @if ($page->type === 'page')
                    <x-form.checkbox name="show_in_footer" :label="__('Link in the footer')" :checked="$page->show_in_footer" />
                @endif
                <x-form.button class="w-full">{{ __('Save') }}</x-form.button>
            </x-card>
            @if ($page->exists)
                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('{{ __('Delete this page?') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-700 hover:underline">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>
    </form>
</x-layouts.panel>
