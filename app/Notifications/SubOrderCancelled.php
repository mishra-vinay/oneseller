<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Goes to the buyer (with reason), the seller (confirmation) and admins (alert).
 */
class SubOrderCancelled extends MarketplaceNotification
{
    public function __construct(public SubOrder $subOrder) {}

    private function roleOf(object $notifiable): Role
    {
        return $notifiable instanceof User ? $notifiable->role : Role::Admin;
    }

    protected function title(object $notifiable): string
    {
        return "Cancelled: {$this->subOrder->number}";
    }

    protected function link(object $notifiable): string
    {
        return match ($this->roleOf($notifiable)) {
            Role::Buyer => route('order.show', $this->subOrder->order),
            Role::Seller => route('seller.orders.show', $this->subOrder->id),
            Role::Admin => route('admin.orders.show', $this->subOrder->order),
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("Cancelled: {$this->subOrder->number}");

        return match ($this->roleOf($notifiable)) {
            Role::Buyer => $mail
                ->line("The part of your order {$this->subOrder->order->number} from {$this->subOrder->store->name} has been cancelled.")
                ->line("Reason: {$this->subOrder->cancel_reason}")
                ->line($this->subOrder->order->payment_method->value === 'online' ? 'Your payment for this part will be refunded to your original payment method.' : 'You will not be charged for these items.')
                ->line('The rest of your order is not affected.')
                ->action('View your order', $this->link($notifiable)),
            Role::Seller => $mail
                ->line("Order {$this->subOrder->number} is cancelled and its stock has been returned.")
                ->line("Reason: {$this->subOrder->cancel_reason}"),
            Role::Admin => $mail
                ->line("{$this->subOrder->store->name}'s portion {$this->subOrder->number} was cancelled by {$this->subOrder->cancelled_by}.")
                ->line("Reason: {$this->subOrder->cancel_reason}")
                ->action('View order', $this->link($notifiable)),
        };
    }
}
