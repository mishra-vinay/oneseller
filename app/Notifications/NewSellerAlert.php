<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Notifications\Messages\MailMessage;

class NewSellerAlert extends MarketplaceNotification
{
    public function __construct(public Store $store) {}

    protected function title(object $notifiable): string
    {
        return "New seller registered: {$this->store->name}";
    }

    protected function link(object $notifiable): string
    {
        return route('admin.sellers.show', $this->store);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("New seller: {$this->store->name}")
            ->line("{$this->store->name} from {$this->store->city} has registered as a seller.")
            ->line('Status: '.$this->store->status->label())
            ->action('Review seller', $this->link($notifiable));
    }
}
