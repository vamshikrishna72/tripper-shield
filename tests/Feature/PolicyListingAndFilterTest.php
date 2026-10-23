<?php

namespace Tests\Feature;

use App\Enums\PolicyStatus;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyListingAndFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test GET /api/policy-requests supports pagination and status filtering.
     */
    public function test_pagination_and_status_filtering(): void
    {
        // Create 3 ISSUED and 2 RECEIVED
        PolicyRequest::factory()->count(3)->issued()->create();
        PolicyRequest::factory()->count(2)->create(['status' => PolicyStatus::RECEIVED]);

        // Filter by ISSUED
        $responseIssued = $this->getJson('/api/policy-requests?status=ISSUED&per_page=2');
        $responseIssued->assertStatus(200)
            ->assertJson([
                'success' => true,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 2,
                    'total' => 3,
                ],
            ]);

        $this->assertCount(2, $responseIssued->json('data'));
        foreach ($responseIssued->json('data') as $item) {
            $this->assertSame('ISSUED', $item['status']);
        }

        // Filter by RECEIVED
        $responseReceived = $this->getJson('/api/policy-requests?status=RECEIVED');
        $responseReceived->assertStatus(200);
        $this->assertCount(2, $responseReceived->json('data'));
    }

    /**
     * Test GET /api/policy-requests/{id} returns full request details,
     * status, premium snapshot, and complete audit history.
     */
    public function test_get_policy_request_details_with_audit_history(): void
    {
        $policy = PolicyRequest::factory()->issued()->create([
            'customer_name' => 'Amitabh Bachchan',
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $policy->id,
            'from_state' => PolicyStatus::RECEIVED,
            'to_state' => PolicyStatus::VALIDATED,
            'trigger' => 'AUDIT_TEST',
            'reason' => 'Passed pre-check',
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $policy->id,
            'attempt_number' => 1,
            'scenario' => 'SUCCESS',
            'status' => 'SUCCESS',
        ]);

        $response = $this->getJson("/api/policy-requests/{$policy->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $policy->id,
                    'customer_name' => 'Amitabh Bachchan',
                    'status' => 'ISSUED',
                    'policy_number' => $policy->policy_number,
                    'provider_reference' => $policy->provider_reference,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.state_history'));
        $this->assertNotEmpty($response->json('data.issuance_attempts'));
    }

    /**
     * Test GET /api/policy-requests/{id} returns 404 for unknown ID.
     */
    public function test_get_policy_request_returns_404_for_unknown_id(): void
    {
        $randomUuid = Str::uuid()->toString();
        $response = $this->getJson("/api/policy-requests/{$randomUuid}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }
}
