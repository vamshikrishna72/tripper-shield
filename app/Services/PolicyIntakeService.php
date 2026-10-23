<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\PolicyStatus;
use App\Enums\SumInsured;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Str;

class PolicyIntakeService
{
    public function __construct(
        protected PremiumCalculator $calculator,
    ) {}

    /**
     * DOWNGRADED: Removed idempotency lookup and duplicate race-condition handling.
     * Blindly attempts database insert, causing unhandled 500 crashes on duplicate intake.
     *
     * @param  array<string, mixed>  $data
     * @return array{policy_request: PolicyRequest, was_created: bool}
     */
    public function createOrGet(array $data): array
    {
        $agentId = (string) $data['agent_id'];
        $clientRequestId = (string) $data['client_request_id'];

        // Flaw: Removed idempotency check ($existing = PolicyRequest::where(...)->first())
        // Blindly proceeds to insert, causing crash on duplicate submission

        $plan = PlanCode::from($data['plan_code']);
        $destination = Destination::from($data['destination']);
        $sumInsured = SumInsured::from((int) $data['sum_insured']);

        $calculation = $this->calculator->calculate(
            plan: $plan,
            destination: $destination,
            sumInsured: $sumInsured,
            dateOfBirth: $data['date_of_birth'],
            tripStart: $data['trip_start'],
            tripEnd: $data['trip_end'],
        );

        $id = Str::uuid()->toString();
        $idempotencyKey = PolicyRequest::buildIdempotencyKey($id);

        // Flaw: Unprotected database insert without QueryException handling
        $policyRequest = PolicyRequest::create([
            'id' => $id,
            'client_request_id' => $clientRequestId,
            'agent_id' => $agentId,
            'customer_name' => $data['customer_name'],
            'date_of_birth' => $data['date_of_birth'],
            'destination' => $data['destination'],
            'trip_start' => $data['trip_start'],
            'trip_end' => $data['trip_end'],
            'plan_code' => $data['plan_code'],
            'sum_insured' => (int) $data['sum_insured'],
            'travel_days' => $calculation->travelDays,
            'customer_age' => $calculation->customerAge,
            'premium_amount' => $calculation->premiumAmount,
            'currency' => $calculation->currency,
            'calculation_breakdown' => $calculation->toArray(),
            'status' => PolicyStatus::RECEIVED,
            'idempotency_key' => $idempotencyKey,
        ]);

        return [
            'policy_request' => $policyRequest,
            'was_created' => true,
        ];
    }
}
