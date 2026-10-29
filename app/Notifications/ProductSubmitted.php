<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;

class ProductSubmitted extends MarketplaceNotification
{
    public function __construct(public Product $product) {}

    protected function title(object $notifiable): string
    {
        return "Product awaiting approval: {$this->product->name}";
    }

    protected function link(object $notifiable): string
    {
        return route('admin.products.index');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title($notifiable))
            ->line("{$this->product->store->name} submitted \"{$this->product->name}\" for approval.")
            ->action('Review products', $this->link($notifiable));
    }
}
