<?php

namespace App\Notifications;

use App\Models\SubOrder;
use Illuminate\Notifications\Messages\MailMessage;

class SubOrderShipped extends MarketplaceNotification
{
    public function __construct(public SubOrder $subOrder) {}

    protected function title(object $notifiable): string
    {
        return "Shipped: {$this->subOrder->number}";
    }

    protected function link(object $notifiable): string
    {
        return route('order.show', $this->subOrder->order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Your parcel from {$this->subOrder->store->name} has shipped")
            ->line("Part of your order {$this->subOrder->order->number} is on its way.")
            ->line("Courier: {$this->subOrder->courier}")
            ->line("Tracking number: {$this->subOrder->tracking_number}")
            ->action('Track your order', $this->link($notifiable));
    }
}
