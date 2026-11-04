<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Store $store, string $product, ReviewService $reviews): RedirectResponse
    {
        $model = Product::visible()->where('store_id', $store->id)->where('slug', $product)->firstOrFail();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:3000'],
        ], ['rating.required' => __('Choose a star rating.')]);

        $reviews->save($request->user(), $model, $data);

        return redirect()->to($model->url().'#reviews')->with('status', __('Thanks for your review!'));
    }
}
