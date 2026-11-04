<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private ImageUploader $images) {}

    public function index(Request $request): View
    {
        return view('admin.pages.index', [
            'pages' => Page::when($request->query('type'), fn ($q, $t) => $q->where('type', $t))->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.pages.form', ['page' => new Page(['type' => $request->query('type') === 'post' ? Page::TYPE_POST : Page::TYPE_PAGE])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = new Page;
        $page->author_id = $request->user()->id;
        $this->save($request, $page);

        return redirect()->route('admin.pages.index')->with('status', __('Saved: :title', ['title' => $page->title]));
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->save($request, $page);

        return redirect()->route('admin.pages.index')->with('status', __('Saved: :title', ['title' => $page->title]));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->images->delete($page->cover_image_path);
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', __('Deleted.'));
    }

    private function save(Request $request, Page $page): void
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);

        $data = $request->validate([
            'type' => ['required', Rule::in([Page::TYPE_PAGE, Page::TYPE_POST])],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'alpha_dash', 'max:200', Rule::unique('pages', 'slug')->ignore($page->id)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:100000'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $page->fill([
            ...collect($data)->except('cover')->all(),
            'show_in_footer' => $request->boolean('show_in_footer'),
            'is_published' => $request->boolean('is_published'),
            'published_at' => $data['published_at'] ?? ($request->boolean('is_published') ? ($page->published_at ?? now()) : null),
        ]);

        if ($request->hasFile('cover')) {
            $this->images->delete($page->cover_image_path);
            $page->cover_image_path = $this->images->store($request->file('cover'), 'pages');
        }

        $page->save();
    }
}
