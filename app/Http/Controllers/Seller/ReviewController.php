<?php

namespace App\Http\Controllers\Seller;

use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends SellerController
{
    public function index(Request $request): View
    {
        return view('seller.reviews', [
            'reviews' => $this->query($request)->with(['product', 'user'])->latest()->paginate(30),
        ]);
    }

    public function reply(Request $request, int $review, ReviewService $reviews): RedirectResponse
    {
        $reply = $request->validate(['reply' => ['nullable', 'string', 'max:2000']])['reply'] ?? null;

        $reviews->reply($this->query($request)->findOrFail($review), $reply);

        return back()->with('status', $reply ? __('Reply posted.') : __('Reply removed.'));
    }

    private function query(Request $request)
    {
        return Review::whereHas('product', fn ($q) => $q->where('store_id', $this->currentStore($request)->id));
    }
}
