<?php

namespace App\Services\MockInsurer;

use App\Enums\MockScenario;
use App\Exceptions\MockInsurerTimeoutException;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class MockInsurerClient
{
    /**
     * Submit policy issuance to the mock insurer.
     *
     * @throws MockInsurerTimeoutException
     */
    public function submitPolicy(
        PolicyRequest $request,
        ?MockScenario $scenario = null,
    ): MockInsurerResponse {
        $effectiveScenario = $this->resolveScenario($scenario);

        // Check if insurer already processed this idempotency key
        $existing = MockInsurerPolicy::where('idempotency_key', $request->idempotency_key)->first();
        if ($existing && $existing->status === 'ISSUED') {
            return new MockInsurerResponse(
                status: 'SUCCESS',
                httpCode: 200,
                providerReference: $existing->provider_reference,
                policyNumber: $existing->policy_number,
                rawResponse: [
                    'provider_reference' => $existing->provider_reference,
                    'policy_number' => $existing->policy_number,
                    'status' => 'ISSUED',
                    'note' => 'Idempotent provider response from existing issuance record',
                ]
            );
        }

        return match ($effectiveScenario) {
            MockScenario::TEMPORARY_FAILURE => $this->handleTemporaryFailure($request),
            MockScenario::TIMEOUT_AFTER_ISSUE => $this->handleTimeoutAfterIssue($request),
            MockScenario::DELAYED_CALLBACK => $this->handleDelayedCallback($request),
            MockScenario::DUPLICATE_CALLBACK => $this->handleDelayedCallback($request),
            MockScenario::SUCCESS => $this->handleSuccess($request),
        };
    }

    /**
     * Lookup existing policy at the provider by idempotency key.
     */
    public function lookupPolicy(string $idempotencyKey): ?MockInsurerPolicy
    {
        return MockInsurerPolicy::where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * Resolve effective scenario, honoring development/testing overrides.
     */
    protected function resolveScenario(?MockScenario $scenario): MockScenario
    {
        if ($scenario !== null) {
            return $scenario;
        }

        // Allow scenario selection header strictly in non-production environments
        if (App::environment('local', 'testing')) {
            $header = request()->header('X-Mock-Scenario');
            if ($header && MockScenario::tryFrom($header)) {
                return MockScenario::from($header);
            }
        }

        return MockScenario::SUCCESS;
    }

    /**
     * Scenario A: SUCCESS
     */
    protected function handleSuccess(PolicyRequest $request): MockInsurerResponse
    {
        $providerRef = 'INS-REF-' . strtoupper(Str::random(10));
        $policyNumber = 'POL-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        $providerPolicy = MockInsurerPolicy::create([
            'idempotency_key' => $request->idempotency_key,
            'provider_reference' => $providerRef,
            'policy_number' => $policyNumber,
            'client_request_id' => $request->client_request_id,
            'customer_name' => $request->customer_name,
            'premium_amount' => $request->premium_amount,
            'status' => 'ISSUED',
        ]);

        return new MockInsurerResponse(
            status: 'SUCCESS',
            httpCode: 200,
            providerReference: $providerPolicy->provider_reference,
            policyNumber: $providerPolicy->policy_number,
            rawResponse: [
                'provider_reference' => $providerPolicy->provider_reference,
                'policy_number' => $providerPolicy->policy_number,
                'status' => 'ISSUED',
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Scenario B: TEMPORARY_FAILURE
     */
    protected function handleTemporaryFailure(PolicyRequest $request): MockInsurerResponse
    {
        return new MockInsurerResponse(
            status: 'FAILED',
            httpCode: 503,
            errorMessage: 'Simulated temporary insurer gateway unavailability (503 Service Unavailable).',
            isRetriable: true,
            rawResponse: [
                'error_code' => 'GATEWAY_TEMPORARY_ERROR',
                'message' => 'Simulated temporary insurer gateway unavailability',
                'retriable' => true,
            ]
        );
    }

    /**
     * Scenario C: TIMEOUT_AFTER_ISSUE
     *
     * The insurer generates and commits the policy to its database,
     * but the network connection times out before returning to caller.
     */
    protected function handleTimeoutAfterIssue(PolicyRequest $request): MockInsurerResponse
    {
        $providerRef = 'INS-TIMEOUT-' . strtoupper(Str::random(10));
        $policyNumber = 'POL-' . date('Ymd') . '-' . strtoupper(Str::random(6));

        // Insurer records the policy on its side
        MockInsurerPolicy::firstOrCreate(
            ['idempotency_key' => $request->idempotency_key],
            [
                'provider_reference' => $providerRef,
                'policy_number' => $policyNumber,
                'client_request_id' => $request->client_request_id,
                'customer_name' => $request->customer_name,
                'premium_amount' => $request->premium_amount,
                'status' => 'ISSUED',
            ]
        );

        // Caller experiences simulated connection timeout
        throw new MockInsurerTimeoutException(
            'Simulated socket timeout after insurer generated policy.'
        );
    }

    /**
     * Scenario D: DELAYED_CALLBACK
     */
    protected function handleDelayedCallback(PolicyRequest $request): MockInsurerResponse
    {
        $providerRef = 'INS-ASYNC-' . strtoupper(Str::random(10));

        MockInsurerPolicy::firstOrCreate(
            ['idempotency_key' => $request->idempotency_key],
            [
                'provider_reference' => $providerRef,
                'policy_number' => 'POL-PENDING-' . strtoupper(Str::random(6)),
                'client_request_id' => $request->client_request_id,
                'customer_name' => $request->customer_name,
                'premium_amount' => $request->premium_amount,
                'status' => 'PENDING',
            ]
        );

        return new MockInsurerResponse(
            status: 'PENDING',
            httpCode: 202,
            providerReference: $providerRef,
            rawResponse: [
                'provider_reference' => $providerRef,
                'status' => 'PENDING',
                'message' => 'Policy issuance request queued. Expect asynchronous callback event.',
            ]
        );
    }
}
