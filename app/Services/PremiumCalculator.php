<?php

namespace App\Services;

use App\DTO\CalculationResult;
use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\SumInsured;
use App\Exceptions\InvalidCalculationException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PremiumCalculator
{
    /**
     * Calculate premium for the policy request with decimal-safe arithmetic.
     *
     * @throws InvalidCalculationException
     */
    public function calculate(
        PlanCode $plan,
        Destination $destination,
        SumInsured $sumInsured,
        CarbonInterface|string $dateOfBirth,
        CarbonInterface|string $tripStart,
        CarbonInterface|string $tripEnd,
    ): CalculationResult {
        $dob = $dateOfBirth instanceof CarbonInterface ? $dateOfBirth->copy()->startOfDay() : Carbon::parse($dateOfBirth)->startOfDay();
        $start = $tripStart instanceof CarbonInterface ? $tripStart->copy()->startOfDay() : Carbon::parse($tripStart)->startOfDay();
        $end = $tripEnd instanceof CarbonInterface ? $tripEnd->copy()->startOfDay() : Carbon::parse($tripEnd)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidCalculationException('trip_end must be on or after trip_start.');
        }

        if ($dob->gt($start)) {
            throw new InvalidCalculationException('date_of_birth cannot be after trip_start.');
        }

        // Travel days inclusive, minimum 1 day
        $travelDays = (int) $start->diffInDays($end) + 1;
        if ($travelDays < 1) {
            $travelDays = 1;
        }

        // Customer age on trip_start
        $customerAge = (int) $dob->diffInYears($start);

        $baseDailyRate = $plan->baseDailyRate();
        $ageMultiplier = $this->getAgeMultiplier($customerAge);
        $destinationMultiplier = $destination->multiplier();
        $coverageMultiplier = $sumInsured->multiplier();

        // High precision decimal multiplication using BCMath
        $accum = bcmul($baseDailyRate, (string) $travelDays, 6);
        $accum = bcmul($accum, $ageMultiplier, 6);
        $accum = bcmul($accum, $destinationMultiplier, 6);
        $accum = bcmul($accum, $coverageMultiplier, 6);

        // Standard financial half-up rounding to 2 decimal places
        $rounded = round((float) $accum, 2, PHP_ROUND_HALF_UP);
        $premiumAmount = number_format($rounded, 2, '.', '');

        return new CalculationResult(
            travelDays: $travelDays,
            customerAge: $customerAge,
            baseDailyRate: $baseDailyRate,
            ageMultiplier: $ageMultiplier,
            destinationMultiplier: $destinationMultiplier,
            coverageMultiplier: $coverageMultiplier,
            premiumAmount: $premiumAmount,
            currency: 'INR',
        );
    }

    /**
     * Determine age multiplier based on age at trip start.
     *
     * Age 0–17: 1.10
     * Age 18–60: 1.00
     * Age 61–70: 1.50
     * Age 71+: 2.00
     */
    public function getAgeMultiplier(int $age): string
    {
        return match (true) {
            $age <= 17 => '1.10',
            $age <= 60 => '1.00',
            $age <= 70 => '1.50',
            default => '2.00',
        };
    }
}
