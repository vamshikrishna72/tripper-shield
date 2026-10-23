<?php

namespace Tests\Unit;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\SumInsured;
use App\Exceptions\InvalidCalculationException;
use App\Services\PremiumCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorTest extends TestCase
{
    protected PremiumCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PremiumCalculator();
    }

    /**
     * Test the exact verified example from specification:
     * PLUS, 5 travel days, age multiplier 1.00, INTERNATIONAL, ₹300000 coverage must produce ₹225.00.
     * Calculation: 20 * 5 * 1.00 * 1.80 * 1.25 = 225.00
     */
    public function test_specification_example_produces_exact_225(): void
    {
        $dob = '1995-05-15'; // 30 years old -> multiplier 1.00
        $tripStart = '2025-06-01';
        $tripEnd = '2025-06-05'; // 5 inclusive days

        $result = $this->calculator->calculate(
            plan: PlanCode::PLUS,
            destination: Destination::INTERNATIONAL,
            sumInsured: SumInsured::INR_300K,
            dateOfBirth: $dob,
            tripStart: $tripStart,
            tripEnd: $tripEnd,
        );

        $this->assertSame(5, $result->travelDays);
        $this->assertSame(30, $result->customerAge);
        $this->assertSame('20.00', $result->baseDailyRate);
        $this->assertSame('1.00', $result->ageMultiplier);
        $this->assertSame('1.80', $result->destinationMultiplier);
        $this->assertSame('1.25', $result->coverageMultiplier);
        $this->assertSame('225.00', $result->premiumAmount);
    }

    /**
     * Test all age multiplier boundary conditions:
     * - Age 0–17: 1.10
     * - Age 18–60: 1.00
     * - Age 61–70: 1.50
     * - Age 71+: 2.00
     */
    public function test_all_age_multiplier_boundaries(): void
    {
        $this->assertSame('1.10', $this->calculator->getAgeMultiplier(0));
        $this->assertSame('1.10', $this->calculator->getAgeMultiplier(16));
        $this->assertSame('1.10', $this->calculator->getAgeMultiplier(17));

        $this->assertSame('1.00', $this->calculator->getAgeMultiplier(18));
        $this->assertSame('1.00', $this->calculator->getAgeMultiplier(40));
        $this->assertSame('1.00', $this->calculator->getAgeMultiplier(60));

        $this->assertSame('1.50', $this->calculator->getAgeMultiplier(61));
        $this->assertSame('1.50', $this->calculator->getAgeMultiplier(65));
        $this->assertSame('1.50', $this->calculator->getAgeMultiplier(70));

        $this->assertSame('2.00', $this->calculator->getAgeMultiplier(71));
        $this->assertSame('2.00', $this->calculator->getAgeMultiplier(85));
    }

    /**
     * Test plan code daily rates:
     * BASIC = ₹12, PLUS = ₹20, PREMIUM = ₹30.
     */
    public function test_all_plan_base_rates(): void
    {
        $start = '2025-01-01';
        $end = '2025-01-01'; // 1 day
        $dob = '2000-01-01'; // age 25 -> 1.00

        // BASIC, DOMESTIC (1.0), 100K (1.0): 12 * 1 * 1.0 * 1.0 * 1.0 = 12.00
        $basic = $this->calculator->calculate(PlanCode::BASIC, Destination::DOMESTIC, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('12.00', $basic->premiumAmount);

        // PLUS: 20 * 1 * 1.0 * 1.0 * 1.0 = 20.00
        $plus = $this->calculator->calculate(PlanCode::PLUS, Destination::DOMESTIC, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('20.00', $plus->premiumAmount);

        // PREMIUM: 30 * 1 * 1.0 * 1.0 * 1.0 = 30.00
        $premium = $this->calculator->calculate(PlanCode::PREMIUM, Destination::DOMESTIC, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('30.00', $premium->premiumAmount);
    }

    /**
     * Test destination multipliers:
     * DOMESTIC = 1.00, INTERNATIONAL = 1.80.
     */
    public function test_destination_multipliers(): void
    {
        $start = '2025-01-01';
        $end = '2025-01-01';
        $dob = '2000-01-01';

        $domestic = $this->calculator->calculate(PlanCode::BASIC, Destination::DOMESTIC, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('12.00', $domestic->premiumAmount);

        // 12 * 1 * 1.0 * 1.80 * 1.0 = 21.60
        $international = $this->calculator->calculate(PlanCode::BASIC, Destination::INTERNATIONAL, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('21.60', $international->premiumAmount);
    }

    /**
     * Test coverage multipliers:
     * 100K = 1.00, 300K = 1.25, 500K = 1.50.
     */
    public function test_coverage_multipliers(): void
    {
        $start = '2025-01-01';
        $end = '2025-01-01';
        $dob = '2000-01-01';

        // 20 * 1 * 1.0 * 1.0 * 1.0 = 20.00
        $cov100 = $this->calculator->calculate(PlanCode::PLUS, Destination::DOMESTIC, SumInsured::INR_100K, $dob, $start, $end);
        $this->assertSame('20.00', $cov100->premiumAmount);

        // 20 * 1 * 1.0 * 1.0 * 1.25 = 25.00
        $cov300 = $this->calculator->calculate(PlanCode::PLUS, Destination::DOMESTIC, SumInsured::INR_300K, $dob, $start, $end);
        $this->assertSame('25.00', $cov300->premiumAmount);

        // 20 * 1 * 1.0 * 1.0 * 1.50 = 30.00
        $cov500 = $this->calculator->calculate(PlanCode::PLUS, Destination::DOMESTIC, SumInsured::INR_500K, $dob, $start, $end);
        $this->assertSame('30.00', $cov500->premiumAmount);
    }

    /**
     * Test single-day travel inclusive calculation:
     * trip_end == trip_start must equal 1 travel day.
     */
    public function test_single_day_travel_equals_one_day(): void
    {
        $result = $this->calculator->calculate(
            PlanCode::BASIC,
            Destination::DOMESTIC,
            SumInsured::INR_100K,
            '2000-01-01',
            '2025-05-10',
            '2025-05-10'
        );

        $this->assertSame(1, $result->travelDays);
    }

    /**
     * Test exception thrown when trip_end is before trip_start.
     */
    public function test_throws_exception_when_trip_end_before_trip_start(): void
    {
        $this->expectException(InvalidCalculationException::class);
        $this->expectExceptionMessage('trip_end must be on or after trip_start.');

        $this->calculator->calculate(
            PlanCode::BASIC,
            Destination::DOMESTIC,
            SumInsured::INR_100K,
            '2000-01-01',
            '2025-05-10',
            '2025-05-09'
        );
    }

    /**
     * Test exception thrown when date of birth is after trip start.
     */
    public function test_throws_exception_when_dob_after_trip_start(): void
    {
        $this->expectException(InvalidCalculationException::class);
        $this->expectExceptionMessage('date_of_birth cannot be after trip_start.');

        $this->calculator->calculate(
            PlanCode::BASIC,
            Destination::DOMESTIC,
            SumInsured::INR_100K,
            '2025-06-01',
            '2025-05-10',
            '2025-05-15'
        );
    }

    /**
     * Test rounding precision: ensures half-up rounding to two decimal places.
     */
    public function test_decimal_safe_rounding_precision(): void
    {
        // 12 * 3 days * 1.10 (minor) * 1.80 (intl) * 1.25 (300k)
        // = 12 * 3 = 36
        // 36 * 1.10 = 39.6
        // 39.6 * 1.80 = 71.28
        // 71.28 * 1.25 = 89.10
        $result = $this->calculator->calculate(
            PlanCode::BASIC,
            Destination::INTERNATIONAL,
            SumInsured::INR_300K,
            '2015-01-01', // age 10 -> 1.10
            '2025-01-01',
            '2025-01-03' // 3 days
        );

        $this->assertSame('89.10', $result->premiumAmount);
    }
}
