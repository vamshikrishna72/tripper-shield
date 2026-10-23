<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\PolicyStatus;
use App\Enums\SumInsured;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyIntakeService
{
    public function __construct(
        protected PremiumCalculator $calculator,
    ) {}

    /**
     * Intake policy request idempotently.
     *
     * Returns array with 'policy_request' and 'was_created' boolean.
     *
     * @param  array<string, mixed>  $data
     * @return array{policy_request: PolicyRequest, was_created: bool}
     */
    public function createOrGet(array $data): array
    {
        $agentId = (string) $data['agent_id'];
        $clientRequestId = (string) $data['client_request_id'];

        // 1. Fast path: check if request already exists
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing) {
            return [
                'policy_request' => $existing,
                'was_created' => false,
            ];
        }

        // 2. Calculate immutable premium snapshot
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

        // 3. Atomically persist with duplicate race condition protection
        try {
            $created = DB::transaction(function () use ($data, $id, $agentId, $clientRequestId, $idempotencyKey, $calculation) {
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

                PolicyStateHistory::create([
                    'policy_request_id' => $policyRequest->id,
                    'from_state' => null,
                    'to_state' => PolicyStatus::RECEIVED,
                    'trigger' => 'INTAKE_RECEIVED',
                    'reason' => 'Policy intake request accepted and persisted with premium snapshot',
                    'metadata' => $calculation->toArray(),
                ]);

                return $policyRequest;
            });

            return [
                'policy_request' => $created,
                'was_created' => true,
            ];
        } catch (QueryException $e) {
            // Check for concurrent duplicate insertion colliding on unique(agent_id, client_request_id)
            $existing = PolicyRequest::where('agent_id', $agentId)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($existing) {
                return [
                    'policy_request' => $existing,
                    'was_created' => false,
                ];
            }

            throw $e;
        }
    }
}
