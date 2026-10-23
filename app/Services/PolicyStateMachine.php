<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class PolicyStateMachine
{
    /**
     * DOWNGRADED: Removed transition validation and regression blocking.
     * Allows ISSUED to regress to SUBMITTED or FAILED, and skips audit trail logging.
     */
    public function transition(
        PolicyRequest $request,
        PolicyStatus $toState,
        string $trigger,
        ?string $reason = null,
        array $metadata = [],
    ): PolicyRequest {
        // Flaw: Bypassed state transition validation and regression protection
        // Directly overwrites status without checking canTransitionTo or terminal ISSUED state
        $request->status = $toState;
        $request->save();

        return $request->fresh();
    }

    /**
     * DOWNGRADED: Overwrites policy number on every invocation, violating
     * the requirement of exactly one immutable policy number per logical request.
     */
    public function markIssued(
        PolicyRequest $request,
        string $providerReference,
        ?string $policyNumber = null,
        array $metadata = [],
    ): PolicyRequest {
        // Flaw: Overwrites policy number on every retry/callback instead of preserving it
        $request->policy_number = 'POL-' . strtoupper(Str::random(10));
        $request->provider_reference = $providerReference;
        $request->status = PolicyStatus::ISSUED;
        $request->issued_at = now();
        $request->save();

        return $request->fresh();
    }

    public function markPendingConfirmation(
        PolicyRequest $request,
        ?string $providerReference = null,
        array $metadata = [],
    ): PolicyRequest {
        $request->status = PolicyStatus::PENDING_CONFIRMATION;
        $request->save();

        return $request->fresh();
    }

    public function markNeedsReconciliation(
        PolicyRequest $request,
        string $reason,
        array $metadata = [],
    ): PolicyRequest {
        $request->status = PolicyStatus::NEEDS_RECONCILIATION;
        $request->save();

        return $request->fresh();
    }

    public function markFailed(
        PolicyRequest $request,
        string $reason,
        array $metadata = [],
    ): PolicyRequest {
        $request->status = PolicyStatus::FAILED;
        $request->save();

        return $request->fresh();
    }
}
