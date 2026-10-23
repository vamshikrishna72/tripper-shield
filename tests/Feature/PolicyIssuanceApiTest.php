<?php

namespace Tests\Feature;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyIssuanceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement: Successful issuance creates exactly one policy number.
     */
    public function test_successful_issuance_creates_one_policy_number(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
        ]);

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $policy->id,
                    'status' => 'ISSUED',
                ],
            ]);

        $fresh = $policy->fresh();
        $this->assertSame(PolicyStatus::ISSUED, $fresh->status);
        $this->assertNotNull($fresh->policy_number);
        $this->assertNotNull($fresh->provider_reference);
        $this->assertSame(1, $fresh->attempt_count);

        // Verify state history progression
        $states = $fresh->stateHistories->pluck('to_state')->map(fn ($s) => $s->value)->toArray();
        $this->assertContains('VALIDATED', $states);
        $this->assertContains('SUBMITTED', $states);
        $this->assertContains('ISSUED', $states);
    }

    /**
     * Requirement: Never allow repeated submit to create multiple policy numbers or duplicate issuance.
     */
    public function test_repeated_submit_on_issued_policy_is_idempotent(): void
    {
        $policy = PolicyRequest::factory()->issued()->create();
        $originalPolicyNumber = $policy->policy_number;
        $originalProviderRef = $policy->provider_reference;

        $response = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $response->assertStatus(200);

        $fresh = $policy->fresh();
        $this->assertSame($originalPolicyNumber, $fresh->policy_number);
        $this->assertSame($originalProviderRef, $fresh->provider_reference);
    }

    /**
     * Requirement: Temporary failure and safe retry.
     * Original premium remains immutable across retries.
     */
    public function test_temporary_failure_and_safe_retry_preserves_premium(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
            'premium_amount' => '225.00',
        ]);

        // Attempt 1: Encounter TEMPORARY_FAILURE
        $failResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);

        $failResponse->assertStatus(200);
        $this->assertSame(PolicyStatus::FAILED, $policy->fresh()->status);
        $this->assertSame('225.00', (string) $policy->fresh()->premium_amount);
        $this->assertSame(1, $policy->fresh()->attempt_count);

        // Attempt 2: Safe retry with SUCCESS
        $retryResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $retryResponse->assertStatus(200);
        $this->assertSame(PolicyStatus::ISSUED, $policy->fresh()->status);
        $this->assertSame('225.00', (string) $policy->fresh()->premium_amount, 'Original premium snapshot must remain intact.');
        $this->assertSame(2, $policy->fresh()->attempt_count);
        $this->assertNotNull($policy->fresh()->policy_number);
    }

    /**
     * Requirement: Case 2: Timeout after issue followed by reconciliation.
     * - The mock insurer records that issuance occurred despite a timeout.
     * - The application moves to NEEDS_RECONCILIATION.
     * - A reconciliation operation queries the mock insurer.
     * - The application discovers the existing policy and transitions to ISSUED without issuing a second policy.
     */
    public function test_timeout_after_issue_followed_by_reconciliation(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
        ]);

        // 1. Submit triggers TIMEOUT_AFTER_ISSUE
        $timeoutResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        $timeoutResponse->assertStatus(200);
        $this->assertSame(PolicyStatus::NEEDS_RECONCILIATION, $policy->fresh()->status);

        // Verify insurer has policy recorded in its mock store
        $this->assertDatabaseHas('mock_insurer_policies', [
            'idempotency_key' => $policy->idempotency_key,
            'status' => 'ISSUED',
        ]);

        // 2. Reconciliation operation resolves the ambiguous state
        $reconcileResponse = $this->postJson("/api/policy-requests/{$policy->id}/reconcile");

        $reconcileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $policy->id,
                    'status' => 'ISSUED',
                ],
            ]);

        $fresh = $policy->fresh();
        $this->assertSame(PolicyStatus::ISSUED, $fresh->status);
        $this->assertNotNull($fresh->policy_number);
        $this->assertNotNull($fresh->provider_reference);

        // Verify the policy number matches the mock insurer's record (no second policy created)
        $this->assertDatabaseHas('mock_insurer_policies', [
            'idempotency_key' => $policy->idempotency_key,
            'policy_number' => $fresh->policy_number,
        ]);
    }

    /**
     * Requirement: Asynchronous callback flow & duplicate callback idempotency.
     * - DELAYED_CALLBACK moves policy to PENDING_CONFIRMATION.
     * - Provider webhook transitions policy to ISSUED.
     * - Repeated webhook delivery with same event_id is handled idempotently without re-processing.
     */
    public function test_delayed_callback_and_duplicate_callback_idempotency(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
        ]);

        // Submit with DELAYED_CALLBACK scenario
        $submitResponse = $this->postJson("/api/policy-requests/{$policy->id}/submit", [
            'scenario' => 'DELAYED_CALLBACK',
        ]);

        $submitResponse->assertStatus(200);
        $this->assertSame(PolicyStatus::PENDING_CONFIRMATION, $policy->fresh()->status);
        $providerRef = $policy->fresh()->provider_reference;

        $eventId = 'EVT-' . Str::uuid()->toString();
        $callbackPayload = [
            'event_id' => $eventId,
            'event_type' => 'POLICY_ISSUED',
            'provider_reference' => $providerRef,
            'policy_number' => 'POL-ASYNC-SUCCESS-88',
            'policy_request_id' => $policy->id,
        ];

        // First callback delivery: transitions to ISSUED
        $firstCallback = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $firstCallback->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_id' => $eventId,
                    'processed' => true,
                ],
            ]);

        $this->assertSame(PolicyStatus::ISSUED, $policy->fresh()->status);
        $this->assertSame('POL-ASYNC-SUCCESS-88', $policy->fresh()->policy_number);

        // Second duplicate callback delivery with identical event_id
        $secondCallback = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $secondCallback->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_id' => $eventId,
                ],
            ]);

        // Event database count remains 1 for this event_id
        $this->assertSame(1, ProviderEvent::where('event_id', $eventId)->count());
        $this->assertSame(PolicyStatus::ISSUED, $policy->fresh()->status);
    }

    /**
     * Requirement: An issued policy cannot be overwritten or regressed by a repeated or stale callback.
     */
    public function test_issued_policy_cannot_be_overwritten_by_stale_callback(): void
    {
        $policy = PolicyRequest::factory()->issued()->create([
            'policy_number' => 'POL-IMMUTABLE-001',
            'provider_reference' => 'INS-IMMUTABLE-001',
        ]);

        $staleEventId = 'EVT-STALE-' . Str::uuid()->toString();
        $stalePayload = [
            'event_id' => $staleEventId,
            'event_type' => 'POLICY_REJECTED', // Stale rejection event attempting to regress ISSUED
            'provider_reference' => $policy->provider_reference,
            'policy_request_id' => $policy->id,
        ];

        $response = $this->postJson('/api/mock-insurer/callback', $stalePayload);
        $response->assertStatus(200);

        $fresh = $policy->fresh();
        $this->assertSame(PolicyStatus::ISSUED, $fresh->status, 'Issued status must remain terminal.');
        $this->assertSame('POL-IMMUTABLE-001', $fresh->policy_number);
    }
}
