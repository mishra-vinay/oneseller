<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Order;
use App\Notifications\NewOrderAlert;
use App\Notifications\NewSubOrder;
use App\Notifications\OrderConfirmation;
use App\Support\AdminNotifier;

/**
 * Side effects of an order becoming real: paid online, or placed with Cash on Delivery.
 */
class OrderEvents
{
    public function placed(Order $order): void
    {
        $order->load(['buyer', 'subOrders.store.user', 'subOrders.items']);

        Activity::record('order.placed', "Order {$order->number} placed", $order);

        $order->buyer->notify(new OrderConfirmation($order));

        // Each seller is told about their own items only.
        foreach ($order->subOrders as $subOrder) {
            $subOrder->store->user->notify(new NewSubOrder($subOrder));
        }

        AdminNotifier::send(new NewOrderAlert($order));
    }
}
