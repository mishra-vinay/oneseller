<x-layouts.panel :title="__('Pages & blog')" area="admin">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2 text-sm">
            @foreach (['' => __('All'), 'page' => __('Pages'), 'post' => __('Blog posts')] as $type => $label)
                <a href="{{ route('admin.pages.index', array_filter(['type' => $type])) }}" @class(['rounded-full px-3 py-1', 'bg-stone-900 text-white' => request('type', '') === $type, 'border border-stone-300 bg-white' => request('type', '') !== $type])>{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.pages.create') }}" class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium hover:bg-stone-50">{{ __('New page') }}</a>
            <a href="{{ route('admin.pages.create', ['type' => 'post']) }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ __('New blog post') }}</a>
        </div>
    </div>
    <div class="rounded-lg border border-stone-200 bg-white">
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse ($pages as $page)
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <span>
                        <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium hover:underline">{{ $page->title }}</a>
                        <span class="text-xs text-stone-500">· {{ $page->type === 'post' ? __('Blog post') : __('Page') }} · {{ $page->url() }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        @if ($page->show_in_footer) <x-badge status="footer" /> @endif
                        <x-badge :status="$page->is_published ? 'active' : 'draft'" />
                    </span>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-stone-500">{{ __('No pages yet.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $pages->links() }}</div>
</x-layouts.panel>
