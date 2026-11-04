<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.reviews', [
            'reviews' => Review::with(['product.store', 'user'])
                ->when($request->query('rating'), fn ($q, $r) => $q->where('rating', $r))
                ->when($request->boolean('hidden'), fn ($q) => $q->where('is_hidden', true))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function toggle(Review $review, ReviewService $reviews): RedirectResponse
    {
        $reviews->setHidden($review, ! $review->is_hidden);
        Activity::record($review->is_hidden ? 'review.hidden' : 'review.shown', "Review on {$review->product->name} ".($review->is_hidden ? 'hidden' : 'shown again'), $review->product);

        return back()->with('status', $review->is_hidden ? __('Review hidden from the storefront.') : __('Review visible again.'));
    }
}
