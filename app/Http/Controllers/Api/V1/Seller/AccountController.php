<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Earnings, reviews and in-panel notifications for the seller app.
 */
class AccountController extends SellerApiController
{
    public function earnings(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $entries = $store->ledgerEntries()->orderByDesc('id')->paginate(50);

        return response()->json([
            'balance' => $store->balance(),
            'data' => LedgerEntryResource::collection($entries->getCollection()),
            'meta' => ['current_page' => $entries->currentPage(), 'last_page' => $entries->lastPage(), 'total' => $entries->total()],
        ]);
    }

    public function reviews(Request $request): AnonymousResourceCollection
    {
        return ReviewResource::collection($this->reviewQuery($request)->with('user')->latest()->paginate(30));
    }

    public function reply(Request $request, int $review, ReviewService $reviews): ReviewResource
    {
        $model = $this->reviewQuery($request)->findOrFail($review);
        $reviews->reply($model, $request->validate(['reply' => ['nullable', 'string', 'max:2000']])['reply'] ?? null);

        return new ReviewResource($model->fresh('user'));
    }

    public function notifications(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection($request->user()->notifications()->paginate(30));
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => __('All notifications marked as read.')]);
    }

    private function reviewQuery(Request $request)
    {
        return Review::whereHas('product', fn ($q) => $q->where('store_id', $this->currentStore($request)->id));
    }
}
