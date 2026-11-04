<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $request->user()->wishlist()->visible()->with(['store', 'images', 'defaultVariant', 'variants'])->orderByPivot('created_at', 'desc')->paginate(24)
        );
    }

    public function add(Request $request, int $product): JsonResponse
    {
        $model = Product::visible()->findOrFail($product);
        $request->user()->wishlist()->syncWithoutDetaching([$model->id => ['created_at' => now()]]);

        return response()->json(['message' => __('Saved to your wishlist.')], 201);
    }

    public function remove(Request $request, int $product): JsonResponse
    {
        $request->user()->wishlist()->detach($product);

        return response()->json(null, 204);
    }
}
