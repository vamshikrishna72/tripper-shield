<?php

namespace App\Http\Controllers\Api;

use App\Enums\MockScenario;
use App\Enums\PolicyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\PolicyIntakeService;
use App\Services\PolicyIssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyIntakeService $intakeService,
        protected PolicyIssuanceService $issuanceService,
    ) {}

    /**
     * POST /api/policy-requests
     * Create a new policy request or idempotently return existing request.
     */
    public function store(StorePolicyRequest $request): JsonResponse
    {
        $result = $this->intakeService->createOrGet($request->validated());
        $policyRequest = $result['policy_request'];
        $wasCreated = $result['was_created'];

        $statusCode = $wasCreated ? 201 : 200;

        return response()->json([
            'success' => true,
            'message' => $wasCreated
                ? 'Policy request successfully created and premium snapshot calculated.'
                : 'Existing policy request returned idempotently.',
            'is_duplicate' => ! $wasCreated,
            'data' => new PolicyRequestResource($policyRequest),
        ], $statusCode);
    }

    /**
     * GET /api/policy-requests/{id}
     * Return request details, current status, premium, provider reference,
     * policy number when available, and audit history.
     */
    public function show(string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])
            ->find($id);

        if (! $policyRequest) {
            return response()->json([
                'success' => false,
                'message' => "Policy request not found for ID: {$id}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PolicyRequestResource($policyRequest),
        ], 200);
    }

    /**
     * GET /api/policy-requests
     * Paginated list of policy requests with optional status and agent filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query();

        // Optional status filter
        if ($request->filled('status')) {
            $statusVal = strtoupper((string) $request->query('status'));
            if ($status = PolicyStatus::tryFrom($statusVal)) {
                $query->where('status', $status);
            }
        }

        // Optional agent_id filter
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PolicyRequestResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * POST /api/policy-requests/{id}/submit
     * Start or safely resume issuance.
     * Prevents repeated HTTP requests from causing duplicate provider issuance.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->find($id);

        if (! $policyRequest) {
            return response()->json([
                'success' => false,
                'message' => "Policy request not found for ID: {$id}",
            ], 404);
        }

        // Resolve scenario override for dev/test environments
        $scenario = null;
        if (App::environment('local', 'testing')) {
            $scenarioInput = $request->input('scenario') ?: $request->header('X-Mock-Scenario');
            if ($scenarioInput && MockScenario::tryFrom($scenarioInput)) {
                $scenario = MockScenario::from($scenarioInput);
            }
        }

        $updated = $this->issuanceService->submit($policyRequest, $scenario);
        $updated->load(['stateHistories', 'issuanceAttempts']);

        return response()->json([
            'success' => true,
            'message' => "Issuance submitted. Current status: {$updated->status->value}",
            'data' => new PolicyRequestResource($updated),
        ], 200);
    }

    /**
     * POST /api/policy-requests/{id}/reconcile
     * Explicit reconciliation operation querying mock insurer to resolve ambiguous states.
     */
    public function reconcile(string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->find($id);

        if (! $policyRequest) {
            return response()->json([
                'success' => false,
                'message' => "Policy request not found for ID: {$id}",
            ], 404);
        }

        $reconciled = $this->issuanceService->reconcile($policyRequest);
        $reconciled->load(['stateHistories', 'issuanceAttempts']);

        return response()->json([
            'success' => true,
            'message' => "Reconciliation processed. Current status: {$reconciled->status->value}",
            'data' => new PolicyRequestResource($reconciled),
        ], 200);
    }
}
