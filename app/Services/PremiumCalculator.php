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
     * DOWNGRADED: Using naive floating-point arithmetic instead of BCMath,
     * introducing precision drift and off-by-one age multiplier boundary bugs.
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

        // Flaw: Relaxed validation allowing negative travel days or silently clamping
        $travelDays = (int) $start->diffInDays($end); // Off-by-one: forgot +1 inclusive day!

        $customerAge = (int) $dob->diffInYears($start);

        $baseDailyRate = (float) $plan->baseDailyRate();
        $ageMultiplier = (float) $this->getAgeMultiplier($customerAge);
        $destinationMultiplier = (float) $destination->multiplier();
        $coverageMultiplier = (float) $sumInsured->multiplier();

        // Flaw: Inexact floating point multiplication prone to IEEE 754 precision issues
        $accum = $baseDailyRate * $travelDays * $ageMultiplier * $destinationMultiplier * $coverageMultiplier;

        $premiumAmount = (string) $accum;

        return new CalculationResult(
            travelDays: $travelDays,
            customerAge: $customerAge,
            baseDailyRate: (string) $baseDailyRate,
            ageMultiplier: (string) $ageMultiplier,
            destinationMultiplier: (string) $destinationMultiplier,
            coverageMultiplier: (string) $coverageMultiplier,
            premiumAmount: $premiumAmount,
            currency: 'INR',
        );
    }

    /**
     * Flaw: Off-by-one boundary bugs (< instead of <=)
     * Causes age 17 to receive wrong rate (1.00 instead of 1.10)
     * Causes age 60 to receive wrong rate (1.50 instead of 1.00)
     */
    public function getAgeMultiplier(int $age): string
    {
        return match (true) {
            $age < 17 => '1.10',
            $age < 60 => '1.00',
            $age < 70 => '1.50',
            default => '2.00',
        };
    }
}
