<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Activity;
use App\Models\Product;
use App\Notifications\ProductReviewed;

class ProductModeration
{
    public function approve(Product $product): void
    {
        $product->status = ProductStatus::Approved;
        $product->rejection_reason = null;
        $product->approved_at = now();
        $product->save();

        Activity::record('product.approved', "Product approved: {$product->name}", $product);
        $product->store->user->notify(new ProductReviewed($product));
    }

    public function reject(Product $product, string $reason): void
    {
        $product->status = ProductStatus::Rejected;
        $product->rejection_reason = $reason;
        $product->save();

        Activity::record('product.rejected', "Product rejected: {$product->name}", $product);
        $product->store->user->notify(new ProductReviewed($product));
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function approveMany(array $ids): int
    {
        $products = Product::with('store.user')->whereIn('id', $ids)->where('status', ProductStatus::Pending)->get();

        $products->each(fn (Product $product) => $this->approve($product));

        return $products->count();
    }

    /**
     * Admin force-deactivation of a product, independent of the seller's publish toggle.
     */
    public function setDisabled(Product $product, bool $disabled): void
    {
        $product->admin_disabled = $disabled;
        $product->save();

        Activity::record($disabled ? 'product.disabled' : 'product.enabled',
            ($disabled ? 'Product deactivated by admin: ' : 'Product reactivated by admin: ').$product->name, $product);
    }
}
