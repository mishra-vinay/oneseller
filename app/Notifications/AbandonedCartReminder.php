<?php

namespace App\Notifications;

use App\Models\Cart;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class AbandonedCartReminder extends MarketplaceNotification
{
    public function __construct(public Cart $cart) {}

    protected function title(object $notifiable): string
    {
        return 'You left something in your cart';
    }

    protected function link(object $notifiable): string
    {
        return route('cart');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title($notifiable))
            ->greeting("Hello {$notifiable->name},")
            ->line('These are still waiting in your cart:');

        foreach ($this->cart->items()->with('variant.product')->limit(5)->get() as $item) {
            $label = $item->variant->label() ? " ({$item->variant->label()})" : '';
            $mail->line("{$item->quantity} × {$item->variant->product->name}{$label} — ".Money::format($item->variant->price * $item->quantity));
        }

        $unsubscribe = URL::signedRoute('cart-reminders.unsubscribe', ['user' => $notifiable->id]);

        return $mail->action('Go to your cart', $this->link($notifiable))
            ->line('Prices and stock can change, so check out soon.')
            ->line("Don't want these reminders? [Unsubscribe]({$unsubscribe}).");
    }
}
