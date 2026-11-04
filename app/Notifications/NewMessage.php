<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class NewMessage extends MarketplaceNotification
{
    public function __construct(public Message $message) {}

    private function toSeller(object $notifiable): bool
    {
        return $notifiable->role === Role::Seller;
    }

    protected function title(object $notifiable): string
    {
        return $this->toSeller($notifiable)
            ? "New message from {$this->message->conversation->buyer->name}"
            : "New message from {$this->message->conversation->store->name}";
    }

    protected function link(object $notifiable): string
    {
        return $this->toSeller($notifiable)
            ? route('seller.messages.show', $this->message->conversation_id)
            : route('account.messages.show', $this->message->conversation_id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title($notifiable))
            ->line('"'.Str::limit($this->message->body, 300).'"')
            ->action('Reply', $this->link($notifiable))
            ->line('Please keep conversations on '.config('app.name').': never share payment details in messages.');
    }
}
