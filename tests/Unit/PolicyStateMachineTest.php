<?php

namespace Tests\Unit;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Services\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected PolicyStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new PolicyStateMachine();
    }

    /**
     * Test valid lifecycle transition sequence:
     * RECEIVED -> VALIDATED -> SUBMITTED -> ISSUED
     */
    public function test_valid_transition_lifecycle(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
        ]);

        // Transition: RECEIVED -> VALIDATED
        $this->stateMachine->transition($policy, PolicyStatus::VALIDATED, 'TEST_VAL');
        $this->assertSame(PolicyStatus::VALIDATED, $policy->fresh()->status);

        // Transition: VALIDATED -> SUBMITTED
        $this->stateMachine->transition($policy, PolicyStatus::SUBMITTED, 'TEST_SUB');
        $this->assertSame(PolicyStatus::SUBMITTED, $policy->fresh()->status);

        // Transition: SUBMITTED -> ISSUED
        $this->stateMachine->markIssued($policy, 'INS-TEST-123', 'POL-12345');
        $this->assertSame(PolicyStatus::ISSUED, $policy->fresh()->status);
        $this->assertSame('POL-12345', $policy->fresh()->policy_number);
        $this->assertSame('INS-TEST-123', $policy->fresh()->provider_reference);

        // Verify audit history was recorded
        $this->assertCount(3, $policy->fresh()->stateHistories);
    }

    /**
     * Test invalid direct transitions are rejected.
     * e.g., RECEIVED -> ISSUED directly without validation/submission.
     */
    public function test_invalid_transition_throws_exception(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::RECEIVED,
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->stateMachine->transition($policy, PolicyStatus::ISSUED, 'INVALID_JUMP');
    }

    /**
     * Requirement: Terminal state ISSUED must never regress to SUBMITTED or any nonterminal state.
     */
    public function test_issued_state_cannot_regress(): void
    {
        $policy = PolicyRequest::factory()->issued()->create();

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('Terminal state [ISSUED] cannot transition to [SUBMITTED].');

        $this->stateMachine->transition($policy, PolicyStatus::SUBMITTED, 'ATTEMPT_REGRESSION');
    }

    /**
     * Requirement: Exactly one unique policy_number for each successfully issued logical request.
     * Subsequent calls to markIssued must preserve the existing policy number and not generate a new one.
     */
    public function test_mark_issued_is_idempotent_and_preserves_policy_number(): void
    {
        $policy = PolicyRequest::factory()->create([
            'status' => PolicyStatus::SUBMITTED,
        ]);

        $this->stateMachine->markIssued($policy, 'INS-REF-ORIGINAL', 'POL-ORIGINAL-99');
        $initialPolicyNumber = $policy->fresh()->policy_number;

        $this->assertSame('POL-ORIGINAL-99', $initialPolicyNumber);

        // Call markIssued again with different provider ref / number
        $this->stateMachine->markIssued($policy, 'INS-REF-SECOND', 'POL-SECOND-00');

        $this->assertSame('POL-ORIGINAL-99', $policy->fresh()->policy_number, 'Policy number must remain immutable.');
        $this->assertSame('INS-REF-ORIGINAL', $policy->fresh()->provider_reference, 'Provider reference must remain immutable.');
    }
}
