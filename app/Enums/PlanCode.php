<?php

namespace App\Enums;

enum PlanCode: string
{
    case BASIC = 'BASIC';
    case PLUS = 'PLUS';
    case PREMIUM = 'PREMIUM';

    /**
     * Daily base rate in INR.
     */
    public function baseDailyRate(): string
    {
        return match ($this) {
            self::BASIC => '12.00',
            self::PLUS => '20.00',
            self::PREMIUM => '30.00',
        };
    }
}
