<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private ImageUploader $images) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::topLevel()->ordered()
                ->withCount('products')
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'parent_id' => $request->integer('parent') ?: null]),
            'parents' => Category::topLevel()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = new Category;
        $this->save($request, $category);

        return redirect()->route('admin.categories.index')->with('status', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::topLevel()->whereKeyNot($category->id)->ordered()->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->save($request, $category);

        return redirect()->route('admin.categories.index')->with('status', __('Category saved.'));
    }

    /**
     * A category holding products cannot be deleted outright: its products are reassigned first.
     */
    public function confirmDelete(Category $category): View
    {
        return view('admin.categories.delete', [
            'category' => $category->loadCount(['products', 'children']),
            'targets' => Category::whereKeyNot($category->id)
                ->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', '!=', $category->id))
                ->with('parent')->get()->sortBy(fn ($c) => $c->fullName()),
        ]);
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['target' => __('Move or delete its sub-categories first.')]);
        }

        $productCount = $category->products()->count();

        $data = $request->validate([
            'target' => [$productCount > 0 ? 'required' : 'nullable', Rule::exists('categories', 'id'), Rule::notIn([$category->id])],
        ], ['target.required' => __('Choose where to move this category\'s products.')]);

        DB::transaction(function () use ($category, $data, $productCount) {
            if ($productCount > 0) {
                $category->products()->update(['category_id' => $data['target']]);
            }

            $category->delete();
        });

        $this->images->delete($category->image_path);

        return redirect()->route('admin.categories.index')->with('status', __('Category deleted.'));
    }

    private function save(Request $request, Category $category): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', Rule::unique('categories', 'slug')->ignore($category->id)],
            // Two levels only: a parent must itself be a main category.
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn([$category->id])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (filled($data['parent_id'] ?? null) && $category->exists && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('A category with sub-categories cannot become a sub-category.')]);
        }

        $category->fill([
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name'], $category),
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('image')) {
            $this->images->delete($category->image_path);
            $category->image_path = $this->images->store($request->file('image'), 'categories');
        }

        $category->save();
    }

    private function uniqueSlug(string $name, Category $category): string
    {
        if ($category->exists && $category->slug && Str::slug($name) === $category->slug) {
            return $category->slug;
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($i = 2; Category::where('slug', $slug)->whereKeyNot($category->id)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
