<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            default => ucfirst($this->value),
        };
    }
}
