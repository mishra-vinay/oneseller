<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the seller (to act on) and admins (for oversight).
 */
class ReturnRequested extends MarketplaceNotification
{
    public function __construct(public ReturnRequest $return) {}

    private function forSeller(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->role === Role::Seller;
    }

    protected function title(object $notifiable): string
    {
        return "{$this->return->type->label()} requested: {$this->return->number}";
    }

    protected function link(object $notifiable): string
    {
        return $this->forSeller($notifiable)
            ? route('seller.returns.show', $this->return)
            : route('admin.returns.show', $this->return);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $return = $this->return->loadMissing('items.orderItem');
        $mail = (new MailMessage)->subject($this->title($notifiable))
            ->line("A buyer asked for a {$return->type->label()} on {$return->subOrder->number}: {$return->reasonLabel()}.");

        foreach ($return->items as $line) {
            $mail->line("{$line->quantity} × {$line->orderItem->displayName()}");
        }

        return $mail->line('Refund value: '.Money::format($return->refund_amount))
            ->action('Review the request', $this->link($notifiable));
    }
}
