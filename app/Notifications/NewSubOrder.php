<?php

namespace App\Notifications;

use App\Models\SubOrder;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to a seller with their own items only, never other sellers' items or totals.
 */
class NewSubOrder extends MarketplaceNotification
{
    public function __construct(public SubOrder $subOrder) {}

    protected function title(object $notifiable): string
    {
        return "New order {$this->subOrder->number}";
    }

    protected function link(object $notifiable): string
    {
        return route('seller.orders.show', $this->subOrder->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subOrder = $this->subOrder->loadMissing('items', 'order');
        $mail = (new MailMessage)->subject("New order: {$subOrder->number}")
            ->line("You have a new order to fulfil ({$subOrder->order->payment_method->label()}).");

        foreach ($subOrder->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name} ({$item->sku}) — ".Money::format($item->line_total));
        }

        return $mail
            ->line('Items: '.Money::format($subOrder->items_subtotal).' · Commission: '.Money::format($subOrder->commission_amount).' · Net payout: '.Money::format($subOrder->net_payout))
            ->action('Accept the order', $this->link($notifiable));
    }
}
