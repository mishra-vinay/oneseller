<?php

namespace App\Http\Resources;

use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A seller's own parcel. Includes the delivery address and phone, never the buyer's email
 * or other sellers' items.
 *
 * @mixin SubOrder
 */
class SellerSubOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_method' => $order->payment_method->value,
            'items' => OrderItemResource::collection($this->items),
            'items_subtotal' => $this->items_subtotal,
            'shipping' => $this->shipping,
            'commission_rate_bps' => $this->commission_bps,
            'commission' => $this->commission_amount,
            'net_payout' => $this->net_payout,
            'deliver_to' => [
                'name' => $order->ship_name,
                'phone' => $order->ship_phone,
                'address_line1' => $order->ship_address_line1,
                'address_line2' => $order->ship_address_line2,
                'city' => $order->ship_city,
                'state' => $order->ship_state,
                'pincode' => $order->ship_pincode,
            ],
            'courier' => $this->courier,
            'tracking_number' => $this->tracking_number,
            'cancel_reason' => $this->cancel_reason,
            'can_cancel' => $this->status->canBeCancelled(),
            'created_at' => $this->created_at->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
