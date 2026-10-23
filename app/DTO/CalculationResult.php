<?php

namespace App\DTO;

readonly class CalculationResult
{
    public function __construct(
        public int $travelDays,
        public int $customerAge,
        public string $baseDailyRate,
        public string $ageMultiplier,
        public string $destinationMultiplier,
        public string $coverageMultiplier,
        public string $premiumAmount,
        public string $currency = 'INR',
    ) {}

    /**
     * Convert to an array for JSON persistence as snapshot.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'travel_days' => $this->travelDays,
            'customer_age' => $this->customerAge,
            'base_daily_rate' => $this->baseDailyRate,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'premium_amount' => $this->premiumAmount,
            'currency' => $this->currency,
        ];
    }
}
