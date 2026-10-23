<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MockInsurerCallbackRequest;
use App\Services\PolicyIssuanceService;
use Illuminate\Http\JsonResponse;

class MockInsurerCallbackController extends Controller
{
    public function __construct(
        protected PolicyIssuanceService $issuanceService,
    ) {}

    /**
     * POST /api/mock-insurer/callback
     * Receive simulated insurer events and process idempotently.
     */
    public function handleCallback(MockInsurerCallbackRequest $request): JsonResponse
    {
        $event = $this->issuanceService->processCallback($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Provider event received and processed idempotently.',
            'data' => [
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'provider_reference' => $event->provider_reference,
                'processed' => $event->processed,
                'processed_at' => $event->processed_at?->toISOString(),
            ],
        ], 200);
    }
}
