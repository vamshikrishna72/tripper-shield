<?php

namespace App\Enums;

enum SumInsured: int
{
    case INR_100K = 100000;
    case INR_300K = 300000;
    case INR_500K = 500000;

    /**
     * Coverage amount multiplier.
     */
    public function multiplier(): string
    {
        return match ($this) {
            self::INR_100K => '1.00',
            self::INR_300K => '1.25',
            self::INR_500K => '1.50',
        };
    }
}
