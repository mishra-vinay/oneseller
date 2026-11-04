<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

class LowStockDigest extends MarketplaceNotification
{
    /**
     * @param  Collection<int, array{name: string, sku: string, stock: int}>  $items
     */
    public function __construct(public Collection $items) {}

    protected function title(object $notifiable): string
    {
        return trans_choice(':count product is low on stock|:count products are low on stock', $this->items->count());
    }

    protected function link(object $notifiable): string
    {
        return route('seller.inventory', ['low' => 1]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Low stock: '.$this->title($notifiable));

        foreach ($this->items as $item) {
            $mail->line("{$item['name']} ({$item['sku']}): {$item['stock']} left");
        }

        return $mail->action('Update stock', $this->link($notifiable));
    }
}
