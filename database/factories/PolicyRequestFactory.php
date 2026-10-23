<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\PolicyStatus;
use App\Enums\SumInsured;
use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolicyRequest>
 */
class PolicyRequestFactory extends Factory
{
    protected $model = PolicyRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = Str::uuid()->toString();
        $dob = Carbon::now()->subYears(fake()->numberBetween(18, 55))->subDays(fake()->numberBetween(0, 300))->format('Y-m-d');
        $tripStart = Carbon::now()->addDays(fake()->numberBetween(5, 30))->format('Y-m-d');
        $tripEnd = Carbon::parse($tripStart)->addDays(fake()->numberBetween(2, 14))->format('Y-m-d');

        $plan = fake()->randomElement(PlanCode::cases());
        $destination = fake()->randomElement(Destination::cases());
        $sumInsured = fake()->randomElement(SumInsured::cases());

        $calc = (new PremiumCalculator())->calculate(
            plan: $plan,
            destination: $destination,
            sumInsured: $sumInsured,
            dateOfBirth: $dob,
            tripStart: $tripStart,
            tripEnd: $tripEnd,
        );

        return [
            'id' => $id,
            'client_request_id' => 'REQ-' . strtoupper(Str::random(12)),
            'agent_id' => 'AGT-' . fake()->numberBetween(1001, 1050),
            'customer_name' => fake()->name(),
            'date_of_birth' => $dob,
            'destination' => $destination->value,
            'trip_start' => $tripStart,
            'trip_end' => $tripEnd,
            'plan_code' => $plan->value,
            'sum_insured' => $sumInsured->value,
            'travel_days' => $calc->travelDays,
            'customer_age' => $calc->customerAge,
            'premium_amount' => $calc->premiumAmount,
            'currency' => 'INR',
            'calculation_breakdown' => $calc->toArray(),
            'status' => PolicyStatus::RECEIVED,
            'provider_reference' => null,
            'policy_number' => null,
            'idempotency_key' => PolicyRequest::buildIdempotencyKey($id),
            'attempt_count' => 0,
            'last_attempted_at' => null,
            'issued_at' => null,
        ];
    }

    /**
     * Indicate that the policy is issued.
     */
    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolicyStatus::ISSUED,
            'provider_reference' => 'INS-REF-' . strtoupper(Str::random(10)),
            'policy_number' => 'POL-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'issued_at' => now(),
            'attempt_count' => 1,
            'last_attempted_at' => now(),
        ]);
    }

    /**
     * Indicate that the policy needs reconciliation.
     */
    public function needsReconciliation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolicyStatus::NEEDS_RECONCILIATION,
            'attempt_count' => 1,
            'last_attempted_at' => now(),
        ]);
    }
}
