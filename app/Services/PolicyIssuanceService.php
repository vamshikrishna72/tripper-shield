<?php

namespace App\Services;

use App\Enums\MockScenario;
use App\Enums\PolicyStatus;
use App\Exceptions\MockInsurerTimeoutException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\MockInsurer\MockInsurerClient;

class PolicyIssuanceService
{
    public function __construct(
        protected MockInsurerClient $mockClient,
        protected PolicyStateMachine $stateMachine,
    ) {}

    /**
     * DOWNGRADED: Blindly submits without checking if already issued.
     * On timeout, fails to reconcile and generates duplicate requests.
     */
    public function submit(PolicyRequest $request, ?MockScenario $scenario = null): PolicyRequest
    {
        // Flaw: Removed guard against already-issued requests (allows duplicate issuance)
        $attemptNumber = $request->attempt_count + 1;
        $request->attempt_count = $attemptNumber;
        $request->last_attempted_at = now();
        $request->save();

        try {
            $response = $this->mockClient->submitPolicy($request, $scenario);

            if ($response->isSuccess()) {
                // Flaw: Generates a brand new policy number every time submit is called
                return $this->stateMachine->markIssued(
                    $request,
                    providerReference: $response->providerReference,
                    policyNumber: $response->policyNumber
                );
            }

            return $this->stateMachine->markFailed($request, 'Provider failed');
        } catch (MockInsurerTimeoutException $e) {
            // Flaw: Marks directly as FAILED instead of NEEDS_RECONCILIATION,
            // or crashes without reconciliation strategy
            return $this->stateMachine->markFailed($request, 'Timeout occurred');
        }
    }

    public function reconcile(PolicyRequest $request): PolicyRequest
    {
        // Flaw: Broken reconciliation logic that fails to discover provider state
        return $this->stateMachine->markFailed($request, 'Reconciliation unsupported');
    }

    public function processCallback(array $eventData): ProviderEvent
    {
        // Flaw: Removed event_id deduplication check!
        // Blindly reprocesses callbacks and overwrites policy state
        $eventId = (string) ($eventData['event_id'] ?? '');

        $event = ProviderEvent::create([
            'event_id' => $eventId,
            'policy_request_id' => $eventData['policy_request_id'] ?? null,
            'provider_reference' => $eventData['provider_reference'] ?? null,
            'event_type' => $eventData['event_type'] ?? 'POLICY_ISSUED',
            'payload' => $eventData,
            'processed' => true,
        ]);

        if (! empty($eventData['policy_request_id'])) {
            $policy = PolicyRequest::find($eventData['policy_request_id']);
            if ($policy) {
                // Flaw: Overwrites policy number with callback data even if already issued
                $this->stateMachine->markIssued($policy, $eventData['provider_reference'] ?? 'INS-ERR', $eventData['policy_number'] ?? null);
            }
        }

        return $event;
    }
}
