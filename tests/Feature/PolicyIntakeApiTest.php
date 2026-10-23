<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful creation of policy request intake (HTTP 201).
     */
    public function test_successful_policy_request_creation(): void
    {
        $payload = [
            'client_request_id' => 'CR-2025-001',
            'agent_id' => 'AGENT-007',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-04-12',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2025-07-01',
            'trip_end' => '2025-07-05',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
                'data' => [
                    'client_request_id' => 'CR-2025-001',
                    'agent_id' => 'AGENT-007',
                    'customer_name' => 'John Doe',
                    'destination' => 'INTERNATIONAL',
                    'plan_code' => 'PLUS',
                    'sum_insured' => 300000,
                    'travel_days' => 5,
                    'customer_age' => 35,
                    'premium_amount' => '225.00',
                    'currency' => 'INR',
                    'status' => 'RECEIVED',
                ],
            ]);

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'CR-2025-001',
            'agent_id' => 'AGENT-007',
            'status' => 'RECEIVED',
        ]);
    }

    /**
     * Test validation failure for invalid travel dates (trip_end before trip_start).
     */
    public function test_validation_fails_when_trip_end_before_trip_start(): void
    {
        $payload = [
            'client_request_id' => 'CR-2025-002',
            'agent_id' => 'AGENT-007',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1992-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2025-08-10',
            'trip_end' => '2025-08-05', // Before trip_start!
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['trip_end']]);
    }

    /**
     * Test validation failure for unsupported plan code.
     */
    public function test_validation_fails_for_unsupported_plan(): void
    {
        $payload = [
            'client_request_id' => 'CR-2025-003',
            'agent_id' => 'AGENT-007',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1992-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2025-08-01',
            'trip_end' => '2025-08-05',
            'plan_code' => 'ENTERPRISE_GOLD', // Invalid plan!
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['plan_code']]);
    }

    /**
     * Test validation failure for unsupported destination.
     */
    public function test_validation_fails_for_unsupported_destination(): void
    {
        $payload = [
            'client_request_id' => 'CR-2025-004',
            'agent_id' => 'AGENT-007',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1992-01-01',
            'destination' => 'INTERPLANETARY', // Invalid destination!
            'trip_start' => '2025-08-01',
            'trip_end' => '2025-08-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['destination']]);
    }

    /**
     * Test validation failure for unsupported sum insured coverage.
     */
    public function test_validation_fails_for_unsupported_coverage_amount(): void
    {
        $payload = [
            'client_request_id' => 'CR-2025-005',
            'agent_id' => 'AGENT-007',
            'customer_name' => 'Jane Doe',
            'date_of_birth' => '1992-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2025-08-01',
            'trip_end' => '2025-08-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 250000, // Invalid sum insured!
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['sum_insured']]);
    }

    /**
     * Requirement: Case 1: Duplicate intake
     * Repeated requests with the same agent_id and client_request_id return the same logical request.
     * Return HTTP 201 for a new request, HTTP 200 for an idempotent repeat.
     */
    public function test_duplicate_intake_returns_existing_request_with_200(): void
    {
        $payload = [
            'client_request_id' => 'IDEMPOTENT-001',
            'agent_id' => 'AGENT-101',
            'customer_name' => 'Priya Patel',
            'date_of_birth' => '1988-11-20',
            'destination' => 'DOMESTIC',
            'trip_start' => '2025-09-01',
            'trip_end' => '2025-09-04', // 4 days: 12 * 4 * 1.0 * 1.0 * 1.0 = 48.00
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // First intake: 201 Created
        $firstResponse = $this->postJson('/api/policy-requests', $payload);
        $firstResponse->assertStatus(201)
            ->assertJson(['is_duplicate' => false]);

        $createdId = $firstResponse->json('data.id');
        $originalPremium = $firstResponse->json('data.premium_amount');

        // Repeated intake with identical agent_id and client_request_id: 200 OK
        $secondResponse = $this->postJson('/api/policy-requests', $payload);
        $secondResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => true,
                'data' => [
                    'id' => $createdId,
                    'premium_amount' => $originalPremium,
                ],
            ]);

        // Database must still have only 1 record
        $this->assertSame(1, PolicyRequest::where('agent_id', 'AGENT-101')->where('client_request_id', 'IDEMPOTENT-001')->count());
    }

    /**
     * Requirement: Database uniqueness under duplicate submissions
     * Database-level unique constraint prevents multiple records for (agent_id, client_request_id).
     */
    public function test_database_level_uniqueness_enforced(): void
    {
        PolicyRequest::factory()->create([
            'agent_id' => 'LOCKED-AGENT',
            'client_request_id' => 'LOCKED-REQ-001',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempting a direct DB insertion bypassing service must trigger unique constraint violation
        PolicyRequest::factory()->create([
            'agent_id' => 'LOCKED-AGENT',
            'client_request_id' => 'LOCKED-REQ-001',
        ]);
    }
}
