<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Requests\SellerProductInput;
use App\Http\Resources\SellerProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Money fields are integer paise. Send images as multipart "images[]"; set submit=true to send for approval.
 */
class ProductController extends SellerApiController
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = ProductStatus::tryFrom((string) $request->query('status'));

        return SellerProductResource::collection(
            $this->currentStore($request)->products()->with(['variants', 'images'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
                ->latest()->paginate(25)
        );
    }

    public function show(Request $request, int $product): SellerProductResource
    {
        return new SellerProductResource($this->find($request, $product)->load(['variants', 'images']));
    }

    public function store(Request $request): JsonResponse
    {
        $product = $this->products->save(
            $this->currentStore($request),
            null,
            SellerProductInput::validate($request, null, moneyInPaise: true),
            $request->file('images', []),
            [],
            $request->boolean('submit'),
        );

        return (new SellerProductResource($product))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $product): SellerProductResource
    {
        $model = $this->find($request, $product);

        return new SellerProductResource($this->products->save(
            $this->currentStore($request),
            $model,
            SellerProductInput::validate($request, $model, moneyInPaise: true),
            $request->file('images', []),
            array_map('intval', $request->input('remove_images', [])),
            $request->boolean('submit'),
        ));
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $this->products->delete($this->find($request, $product));

        return response()->json(null, 204);
    }

    private function find(Request $request, int $id): Product
    {
        return $this->currentStore($request)->products()->findOrFail($id);
    }
}
