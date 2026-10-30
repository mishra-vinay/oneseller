<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrderAlert extends MarketplaceNotification
{
    public function __construct(public Order $order) {}

    protected function title(object $notifiable): string
    {
        return "New order {$this->order->number} · ".Money::format($this->order->grand_total);
    }

    protected function link(object $notifiable): string
    {
        return route('admin.orders.show', $this->order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("New order: {$this->order->number}")
            ->line('Total '.Money::format($this->order->grand_total).' ('.$this->order->payment_method->label().') across '
                .trans_choice(':count seller|:count sellers', $this->order->subOrders->count()).'.')
            ->action('View order', $this->link($notifiable));
    }
}
