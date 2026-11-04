<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, int $product, ReviewService $reviews): JsonResponse
    {
        $model = Product::visible()->findOrFail($product);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:3000'],
        ]);

        return (new ReviewResource($reviews->save($request->user(), $model, $data)->load('user')))->response()->setStatusCode(201);
    }
}
