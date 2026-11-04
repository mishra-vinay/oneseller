<?php

namespace App\Services;

use App\Enums\SubOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Only buyers who received the product can review it, once per product (they can edit it).
 */
class ReviewService
{
    public function canReview(?User $user, Product $product): bool
    {
        if (! $user) {
            return false;
        }

        return OrderItem::where('product_id', $product->id)
            ->whereHas('subOrder', fn ($q) => $q->where('status', SubOrderStatus::Delivered)
                ->whereHas('order', fn ($o) => $o->where('buyer_id', $user->id)))
            ->exists();
    }

    /**
     * @param  array{rating: int, title?: ?string, body?: ?string}  $data
     */
    public function save(User $user, Product $product, array $data): Review
    {
        if (! $this->canReview($user, $product)) {
            throw ValidationException::withMessages(['rating' => __('You can review a product once it has been delivered to you.')]);
        }

        $review = Review::where('product_id', $product->id)->where('user_id', $user->id)->first()
            ?? (new Review)->forceFill(['product_id' => $product->id, 'user_id' => $user->id]);
        $review->fill($data)->save();

        $this->refreshRating($product);

        return $review;
    }

    public function reply(Review $review, ?string $reply): void
    {
        $review->forceFill(['seller_reply' => $reply ?: null, 'seller_replied_at' => $reply ? now() : null])->save();
    }

    public function setHidden(Review $review, bool $hidden): void
    {
        $review->forceFill(['is_hidden' => $hidden])->save();
        $this->refreshRating($review->product);
    }

    public function refreshRating(Product $product): void
    {
        $stats = $product->reviews()->shown()->selectRaw('count(*) as c, avg(rating) as a')->first();

        $product->forceFill([
            'rating_count' => (int) $stats->c,
            'rating_avg' => round((float) $stats->a, 2),
        ])->saveQuietly();
    }
}
