<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case Cod = 'cod';

    public function label(): string
    {
        return $this === self::Cod ? 'Cash on Delivery' : 'Online payment';
    }
}
