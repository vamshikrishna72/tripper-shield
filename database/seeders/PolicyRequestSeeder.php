<?php

namespace Database\Seeders;

use App\Enums\PolicyStatus;
use App\Models\IssuanceAttempt;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Database\Seeder;

class PolicyRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. A freshly received policy request
        $received = PolicyRequest::factory()->create([
            'client_request_id' => 'CLIENT-REQ-1001',
            'agent_id' => 'AGENT-ALPHA',
            'customer_name' => 'Aditi Sharma',
            'status' => PolicyStatus::RECEIVED,
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $received->id,
            'from_state' => null,
            'to_state' => PolicyStatus::RECEIVED,
            'trigger' => 'INTAKE_RECEIVED',
            'reason' => 'Intake request accepted',
            'metadata' => $received->calculation_breakdown,
        ]);

        // 2. An issued policy request with full audit history
        $issued = PolicyRequest::factory()->issued()->create([
            'client_request_id' => 'CLIENT-REQ-1002',
            'agent_id' => 'AGENT-BETA',
            'customer_name' => 'Rajesh Verma',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $issued->id,
            'from_state' => null,
            'to_state' => PolicyStatus::RECEIVED,
            'trigger' => 'INTAKE_RECEIVED',
            'reason' => 'Intake request accepted',
            'metadata' => $issued->calculation_breakdown,
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $issued->id,
            'from_state' => PolicyStatus::RECEIVED,
            'to_state' => PolicyStatus::VALIDATED,
            'trigger' => 'PRE_SUBMISSION_CHECK',
            'reason' => 'Validation passed',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $issued->id,
            'from_state' => PolicyStatus::VALIDATED,
            'to_state' => PolicyStatus::SUBMITTED,
            'trigger' => 'DISPATCH_TO_PROVIDER',
            'reason' => 'Dispatched to mock insurer',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $issued->id,
            'from_state' => PolicyStatus::SUBMITTED,
            'to_state' => PolicyStatus::ISSUED,
            'trigger' => 'ISSUANCE_CONFIRMED',
            'reason' => 'Insurer issued policy',
            'metadata' => [
                'provider_reference' => $issued->provider_reference,
                'policy_number' => $issued->policy_number,
            ],
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $issued->id,
            'attempt_number' => 1,
            'scenario' => 'SUCCESS',
            'status' => 'SUCCESS',
            'request_payload' => ['client_request_id' => $issued->client_request_id],
            'response_payload' => [
                'provider_reference' => $issued->provider_reference,
                'policy_number' => $issued->policy_number,
            ],
        ]);

        MockInsurerPolicy::create([
            'idempotency_key' => $issued->idempotency_key,
            'provider_reference' => $issued->provider_reference,
            'policy_number' => $issued->policy_number,
            'client_request_id' => $issued->client_request_id,
            'customer_name' => $issued->customer_name,
            'premium_amount' => $issued->premium_amount,
            'status' => 'ISSUED',
        ]);

        // 3. A policy request stuck in NEEDS_RECONCILIATION due to timeout
        $reconcileMe = PolicyRequest::factory()->needsReconciliation()->create([
            'client_request_id' => 'CLIENT-REQ-1003',
            'agent_id' => 'AGENT-GAMMA',
            'customer_name' => 'Kavita Nair',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $reconcileMe->id,
            'from_state' => null,
            'to_state' => PolicyStatus::RECEIVED,
            'trigger' => 'INTAKE_RECEIVED',
            'reason' => 'Intake accepted',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $reconcileMe->id,
            'from_state' => PolicyStatus::RECEIVED,
            'to_state' => PolicyStatus::VALIDATED,
            'trigger' => 'PRE_SUBMISSION_CHECK',
            'reason' => 'Pre-submission check passed',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $reconcileMe->id,
            'from_state' => PolicyStatus::VALIDATED,
            'to_state' => PolicyStatus::SUBMITTED,
            'trigger' => 'DISPATCH_TO_PROVIDER',
            'reason' => 'Dispatched attempt #1',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $reconcileMe->id,
            'from_state' => PolicyStatus::SUBMITTED,
            'to_state' => PolicyStatus::NEEDS_RECONCILIATION,
            'trigger' => 'TIMEOUT_OR_AMBIGUOUS_STATE',
            'reason' => 'Timeout received during insurer submission',
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $reconcileMe->id,
            'attempt_number' => 1,
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
            'status' => 'TIMEOUT',
            'error_message' => 'Simulated socket timeout after insurer generated policy.',
        ]);

        // Insurer record exists on provider side!
        MockInsurerPolicy::create([
            'idempotency_key' => $reconcileMe->idempotency_key,
            'provider_reference' => 'INS-TIMEOUT-SEEDED',
            'policy_number' => 'POL-SEEDED-RECON01',
            'client_request_id' => $reconcileMe->client_request_id,
            'customer_name' => $reconcileMe->customer_name,
            'premium_amount' => $reconcileMe->premium_amount,
            'status' => 'ISSUED',
        ]);
    }
}
