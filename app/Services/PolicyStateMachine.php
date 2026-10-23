<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyStateMachine
{
    /**
     * Perform an explicit validated state transition and record audit history.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        PolicyRequest $request,
        PolicyStatus $toState,
        string $trigger,
        ?string $reason = null,
        array $metadata = [],
    ): PolicyRequest {
        $fromState = $request->status;

        // If target state is the same, no transition needed
        if ($fromState === $toState) {
            return $request;
        }

        // Never allow ISSUED to regress
        if ($fromState === PolicyStatus::ISSUED) {
            throw new InvalidStateTransitionException(
                $fromState,
                $toState,
                "Terminal state [ISSUED] cannot transition to [{$toState->value}]."
            );
        }

        // Enforce allowed state transitions
        if (! $fromState->canTransitionTo($toState)) {
            throw new InvalidStateTransitionException($fromState, $toState);
        }

        return DB::transaction(function () use ($request, $fromState, $toState, $trigger, $reason, $metadata) {
            // Record audit history
            PolicyStateHistory::create([
                'policy_request_id' => $request->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'trigger' => $trigger,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            // Update current state
            $request->status = $toState;
            $request->save();

            return $request->fresh();
        });
    }

    /**
     * Mark policy request as ISSUED with a single unique policy number.
     *
     * @throws InvalidStateTransitionException
     */
    public function markIssued(
        PolicyRequest $request,
        string $providerReference,
        ?string $policyNumber = null,
        array $metadata = [],
    ): PolicyRequest {
        // If already issued, ensure idempotency: never overwrite or mutate existing policy number
        if ($request->isIssued()) {
            return $request;
        }

        return DB::transaction(function () use ($request, $providerReference, $policyNumber, $metadata) {
            // Ensure unique policy number generation if none provided
            if (! $request->policy_number) {
                $request->policy_number = $policyNumber ?: ('POL-' . strtoupper(Str::random(10)));
            }

            $request->provider_reference = $providerReference;
            $request->issued_at = now();
            $request->save();

            return $this->transition(
                $request,
                PolicyStatus::ISSUED,
                'ISSUANCE_CONFIRMED',
                'Policy issuance confirmed by provider',
                array_merge($metadata, [
                    'provider_reference' => $providerReference,
                    'policy_number' => $request->policy_number,
                ])
            );
        });
    }

    /**
     * Transition to PENDING_CONFIRMATION.
     *
     * @throws InvalidStateTransitionException
     */
    public function markPendingConfirmation(
        PolicyRequest $request,
        ?string $providerReference = null,
        array $metadata = [],
    ): PolicyRequest {
        if ($providerReference) {
            $request->provider_reference = $providerReference;
            $request->save();
        }

        return $this->transition(
            $request,
            PolicyStatus::PENDING_CONFIRMATION,
            'ASYNC_PENDING',
            'Provider accepted submission and confirmation is pending',
            $metadata
        );
    }

    /**
     * Transition to NEEDS_RECONCILIATION.
     *
     * @throws InvalidStateTransitionException
     */
    public function markNeedsReconciliation(
        PolicyRequest $request,
        string $reason,
        array $metadata = [],
    ): PolicyRequest {
        return $this->transition(
            $request,
            PolicyStatus::NEEDS_RECONCILIATION,
            'TIMEOUT_OR_AMBIGUOUS_STATE',
            $reason,
            $metadata
        );
    }

    /**
     * Transition to FAILED.
     *
     * @throws InvalidStateTransitionException
     */
    public function markFailed(
        PolicyRequest $request,
        string $reason,
        array $metadata = [],
    ): PolicyRequest {
        return $this->transition(
            $request,
            PolicyStatus::FAILED,
            'PROVIDER_ERROR',
            $reason,
            $metadata
        );
    }
}
