<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
