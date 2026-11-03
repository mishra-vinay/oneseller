<?php

namespace App\Enums;

enum ReturnType: string
{
    case Refund = 'refund';
    case Replacement = 'replacement';

    public function label(): string
    {
        return $this === self::Refund ? 'Refund' : 'Replacement';
    }
}
