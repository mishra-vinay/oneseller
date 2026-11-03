<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Refunded = 'refunded';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting seller',
            self::Approved => 'Approved, send it back',
            self::Received => 'Item received',
            default => ucfirst($this->value),
        };
    }

    /**
     * Statuses that still hold the items (so they cannot be returned twice).
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Requested, self::Approved, self::Received, self::Refunded, self::Replaced];
    }
}
