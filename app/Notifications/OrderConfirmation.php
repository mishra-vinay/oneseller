<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class OrderConfirmation extends MarketplaceNotification
{
    public function __construct(public Order $order) {}

    protected function title(object $notifiable): string
    {
        return "Order {$this->order->number} confirmed";
    }

    protected function link(object $notifiable): string
    {
        return route('order.show', $this->order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('subOrders.store', 'subOrders.items');
        $mail = (new MailMessage)
            ->subject("Order confirmed: {$order->number}")
            ->greeting("Thank you, {$notifiable->name}!")
            ->line("Your order {$order->number} is confirmed. It ships in ".trans_choice(':count parcel|:count parcels', $order->subOrders->count()).', one from each seller.');

        foreach ($order->subOrders as $subOrder) {
            $mail->line("**{$subOrder->store->name}** ({$subOrder->number})");

            foreach ($subOrder->items as $item) {
                $mail->line("{$item->quantity} × {$item->product_name} — ".Money::format($item->line_total));
            }

            $mail->line('Shipping: '.($subOrder->shipping ? Money::format($subOrder->shipping) : 'Free'));
        }

        return $mail
            ->line('**Total: '.Money::format($order->grand_total).'** ('.$order->payment_method->label().')')
            ->action('View your order', $this->link($notifiable));
    }
}
