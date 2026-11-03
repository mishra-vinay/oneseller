<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\StoreResource;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog;
use App\Services\CheckoutOptions;
use App\Services\Payments\RazorpayGateway;
use App\Support\Locale;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private Catalog $catalog) {}

    /**
     * What the app needs to know before checkout.
     */
    public function config(Settings $settings, CheckoutOptions $options, RazorpayGateway $razorpay): JsonResponse
    {
        return response()->json([
            'site_name' => $settings->get('site_name'),
            'currency' => 'INR',
            'money_unit' => 'paise',
            // Display-only: multiply rupees by the rate. Payment is always in INR.
            'currency_rates' => Locale::rates(),
            'languages' => array_keys(Locale::LANGUAGES),
            'payment_methods' => array_map(fn ($m) => $m->value, $options->paymentMethods()),
            'razorpay_key_id' => $razorpay->isConfigured() ? $razorpay->keyId() : null,
            'return_window_days' => $settings->get('return_window_days'),
            'support_email' => $settings->get('support_email'),
        ]);
    }

    /**
     * Content pages and blog posts; body is rendered, safe HTML.
     */
    public function page(string $slug): JsonResponse
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->pageData($page) + ['html' => (string) $page->html()]]);
    }

    public function blog(): JsonResponse
    {
        $posts = Page::published()->where('type', Page::TYPE_POST)->latest('published_at')->paginate(12);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (Page $p) => $this->pageData($p)),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Page $page): array
    {
        return [
            'type' => $page->type,
            'title' => $page->title,
            'slug' => $page->slug,
            'excerpt' => $page->excerpt,
            'cover_url' => $page->coverUrl(),
            'published_at' => $page->published_at?->toIso8601String(),
            'url' => $page->url(),
        ];
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::topLevel()->active()->ordered()->with(['children' => fn ($q) => $q->where('is_active', true)])->get()
        );
    }

    /**
     * Filters: q, category (slug, includes sub-categories), min, max (rupees), seller (store slug), in_stock, sort, page.
     */
    public function products(Request $request): AnonymousResourceCollection
    {
        $query = $this->catalog->search($this->catalog->base(), (string) $request->query('q', ''));

        if ($request->query('category')) {
            $category = Category::active()->where('slug', $request->query('category'))->firstOrFail();
            $query->whereIn('products.category_id', $category->selfAndChildIds());
        }

        return ProductResource::collection($this->catalog->paginate($query, $request));
    }

    public function product(int $product): ProductResource
    {
        $model = Product::visible()->with(['store', 'images', 'defaultVariant', 'variants', 'category'])->findOrFail($product);

        return (new ProductResource($model))->detailed();
    }

    public function reviews(int $product): AnonymousResourceCollection
    {
        $model = Product::visible()->findOrFail($product);

        return ReviewResource::collection($model->reviews()->shown()->with('user')->latest()->paginate(20));
    }

    public function store(Store $store): StoreResource
    {
        abort_unless(Store::live()->whereKey($store->id)->exists(), 404);

        return new StoreResource($store);
    }

    public function storeProducts(Request $request, Store $store): AnonymousResourceCollection
    {
        abort_unless(Store::live()->whereKey($store->id)->exists(), 404);

        return ProductResource::collection($this->catalog->paginate($this->catalog->base()->where('products.store_id', $store->id), $request));
    }
}
