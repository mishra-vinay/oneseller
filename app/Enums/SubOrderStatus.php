<?php

namespace App\Enums;

enum SubOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Placed = 'placed';
    case Accepted = 'accepted';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Placed => 'New',
            default => ucfirst($this->value),
        };
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Placed, self::Accepted], true);
    }

    /**
     * Statuses a seller still has to act on.
     *
     * @return array<int, self>
     */
    public static function awaitingSeller(): array
    {
        return [self::Placed, self::Accepted];
    }
}
