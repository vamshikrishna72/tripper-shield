<?php

namespace App\Enums;

enum Destination: string
{
    case DOMESTIC = 'DOMESTIC';
    case INTERNATIONAL = 'INTERNATIONAL';

    /**
     * Destination rate multiplier.
     */
    public function multiplier(): string
    {
        return match ($this) {
            self::DOMESTIC => '1.00',
            self::INTERNATIONAL => '1.80',
        };
    }
}
