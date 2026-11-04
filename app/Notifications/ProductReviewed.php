<?php

namespace App\Notifications;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;

class ProductReviewed extends MarketplaceNotification
{
    public function __construct(public Product $product) {}

    private function approved(): bool
    {
        return $this->product->status === ProductStatus::Approved;
    }

    protected function title(object $notifiable): string
    {
        return $this->approved()
            ? "Product approved: {$this->product->name}"
            : "Product rejected: {$this->product->name}";
    }

    protected function link(object $notifiable): string
    {
        return route('seller.products.edit', $this->product->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title($notifiable));

        return $this->approved()
            ? $mail->line("\"{$this->product->name}\" is approved and live on the storefront.")
            : $mail->line("\"{$this->product->name}\" was not approved.")
                ->line('Reason: '.$this->product->rejection_reason)
                ->line('You can correct it and submit it again.')
                ->action('Edit product', $this->link($notifiable));
    }
}
