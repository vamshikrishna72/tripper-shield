<?php

namespace App\Services;

use App\Enums\MockScenario;
use App\Enums\PolicyStatus;
use App\Exceptions\MockInsurerTimeoutException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\MockInsurer\MockInsurerClient;
use Illuminate\Support\Facades\DB;
use Throwable;

class PolicyIssuanceService
{
    public function __construct(
        protected MockInsurerClient $mockClient,
        protected PolicyStateMachine $stateMachine,
    ) {}

    /**
     * Start or safely resume policy issuance.
     */
    public function submit(PolicyRequest $request, ?MockScenario $scenario = null): PolicyRequest
    {
        // 1. Guard against repeated submissions for an already issued policy
        if ($request->isIssued()) {
            return $request;
        }

        // 2. If the request was left in an ambiguous state, force reconciliation lookup
        if ($request->status === PolicyStatus::NEEDS_RECONCILIATION) {
            return $this->reconcile($request);
        }

        // 3. Perform pre-submission state transitions
        if ($request->status === PolicyStatus::RECEIVED) {
            $request = $this->stateMachine->transition(
                $request,
                PolicyStatus::VALIDATED,
                'PRE_SUBMISSION_CHECK',
                'Policy request data validated for submission'
            );
        }

        $attemptNumber = $request->attempt_count + 1;
        $request->attempt_count = $attemptNumber;
        $request->last_attempted_at = now();
        $request->save();

        $request = $this->stateMachine->transition(
            $request,
            PolicyStatus::SUBMITTED,
            'DISPATCH_TO_PROVIDER',
            "Dispatching issuance attempt #{$attemptNumber} to insurer"
        );

        $sanitizedRequestPayload = [
            'idempotency_key' => $request->idempotency_key,
            'client_request_id' => $request->client_request_id,
            'plan_code' => $request->plan_code->value,
            'sum_insured' => $request->sum_insured,
            'premium_amount' => $request->premium_amount,
            'travel_days' => $request->travel_days,
        ];

        // 4. Invoke provider (outside of long database transactions)
        try {
            $response = $this->mockClient->submitPolicy($request, $scenario);

            // Record issuance attempt
            IssuanceAttempt::create([
                'policy_request_id' => $request->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario?->value,
                'status' => $response->status,
                'request_payload' => $sanitizedRequestPayload,
                'response_payload' => $response->rawResponse,
                'error_message' => $response->errorMessage,
            ]);

            if ($response->isSuccess()) {
                return $this->stateMachine->markIssued(
                    $request,
                    providerReference: $response->providerReference,
                    policyNumber: $response->policyNumber,
                    metadata: ['attempt_number' => $attemptNumber]
                );
            }

            if ($response->isPending()) {
                return $this->stateMachine->markPendingConfirmation(
                    $request,
                    providerReference: $response->providerReference,
                    metadata: ['attempt_number' => $attemptNumber]
                );
            }

            // Non-success failure response (e.g. temporary 503)
            return $this->stateMachine->markFailed(
                $request,
                $response->errorMessage ?: 'Provider rejected issuance request.',
                ['attempt_number' => $attemptNumber, 'retriable' => $response->isRetriable]
            );

        } catch (MockInsurerTimeoutException $e) {
            // Ambiguous outcome: network timed out. Do NOT blindly retry; transition to NEEDS_RECONCILIATION
            IssuanceAttempt::create([
                'policy_request_id' => $request->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario?->value ?: MockScenario::TIMEOUT_AFTER_ISSUE->value,
                'status' => 'TIMEOUT',
                'request_payload' => $sanitizedRequestPayload,
                'response_payload' => null,
                'error_message' => $e->getMessage(),
            ]);

            return $this->stateMachine->markNeedsReconciliation(
                $request,
                'Timeout received from insurer. Ambiguous outcome requires reconciliation lookup before re-issuing.',
                ['attempt_number' => $attemptNumber]
            );

        } catch (Throwable $e) {
            IssuanceAttempt::create([
                'policy_request_id' => $request->id,
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario?->value,
                'status' => 'FAILED',
                'request_payload' => $sanitizedRequestPayload,
                'response_payload' => null,
                'error_message' => $e->getMessage(),
            ]);

            return $this->stateMachine->markFailed(
                $request,
                'Unexpected exception during insurer dispatch: ' . $e->getMessage(),
                ['attempt_number' => $attemptNumber]
            );
        }
    }

    /**
     * Reconcile an ambiguous state with the insurer using the stable idempotency key.
     */
    public function reconcile(PolicyRequest $request): PolicyRequest
    {
        // If already issued, nothing to reconcile
        if ($request->isIssued()) {
            return $request;
        }

        $providerPolicy = $this->mockClient->lookupPolicy($request->idempotency_key);

        if ($providerPolicy && $providerPolicy->status === 'ISSUED') {
            // Discovered existing provider-side issuance!
            // Transition directly to ISSUED using existing policy number without double issuance.
            return $this->stateMachine->markIssued(
                $request,
                providerReference: $providerPolicy->provider_reference,
                policyNumber: $providerPolicy->policy_number,
                metadata: [
                    'reconciled' => true,
                    'reconciliation_source' => 'mock_provider_lookup',
                    'message' => 'Discovered existing policy at provider during timeout reconciliation.',
                ]
            );
        }

        // Provider has no record of issuance; can be transitioned to FAILED so it can be retried safely
        return $this->stateMachine->markFailed(
            $request,
            'Reconciliation confirmed no policy was issued by insurer for this idempotency key.'
        );
    }

    /**
     * Process an inbound provider callback idempotently.
     */
    public function processCallback(array $eventData): ProviderEvent
    {
        $eventId = (string) ($eventData['event_id'] ?? '');

        // 1. Webhook Deduplication: check if this event_id was already received
        $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
        if ($existingEvent) {
            return $existingEvent;
        }

        return DB::transaction(function () use ($eventData, $eventId) {
            $eventType = (string) ($eventData['event_type'] ?? 'POLICY_ISSUED');
            $idempotencyKey = $eventData['idempotency_key'] ?? null;
            $providerReference = $eventData['provider_reference'] ?? null;
            $policyNumber = $eventData['policy_number'] ?? null;

            // Locate matching policy request
            $query = PolicyRequest::query();
            if (! empty($eventData['policy_request_id'])) {
                $query->where('id', $eventData['policy_request_id']);
            } elseif ($idempotencyKey) {
                $query->where('idempotency_key', $idempotencyKey);
            } elseif ($providerReference) {
                $query->where('provider_reference', $providerReference);
            }

            $policyRequest = $query->first();

            $event = ProviderEvent::create([
                'event_id' => $eventId,
                'policy_request_id' => $policyRequest?->id,
                'provider_reference' => $providerReference,
                'event_type' => $eventType,
                'payload' => $eventData,
                'processed' => false,
            ]);

            if ($policyRequest) {
                // If policy is not yet issued and callback announces success
                if (! $policyRequest->isIssued() && $eventType === 'POLICY_ISSUED') {
                    $this->stateMachine->markIssued(
                        $policyRequest,
                        providerReference: $providerReference ?: ($policyRequest->provider_reference ?: 'INS-CB-' . $eventId),
                        policyNumber: $policyNumber,
                        metadata: ['callback_event_id' => $eventId]
                    );
                }

                $event->processed = true;
                $event->processed_at = now();
                $event->save();
            }

            return $event;
        });
    }
}
