<?php

namespace App\Notifications;

use App\Models\SubOrder;
use Illuminate\Notifications\Messages\MailMessage;

class SubOrderDelivered extends MarketplaceNotification
{
    public function __construct(public SubOrder $subOrder) {}

    protected function title(object $notifiable): string
    {
        return "Delivered: {$this->subOrder->number}";
    }

    protected function link(object $notifiable): string
    {
        return route('order.show', $this->subOrder->order);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Delivered: your parcel from {$this->subOrder->store->name}")
            ->line("Your parcel {$this->subOrder->number} from {$this->subOrder->store->name} has been delivered.")
            ->action('View your order', $this->link($notifiable));
    }
}
