<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_request_id' => $this->client_request_id,
            'agent_id' => $this->agent_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination?->value ?? $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'plan_code' => $this->plan_code?->value ?? $this->plan_code,
            'sum_insured' => (int) $this->sum_insured,
            'travel_days' => (int) $this->travel_days,
            'customer_age' => (int) $this->customer_age,
            'premium_amount' => (string) $this->premium_amount,
            'currency' => $this->currency,
            'calculation_breakdown' => $this->calculation_breakdown,
            'status' => $this->status?->value ?? $this->status,
            'provider_reference' => $this->provider_reference,
            'policy_number' => $this->policy_number,
            'attempt_count' => (int) $this->attempt_count,
            'last_attempted_at' => $this->last_attempted_at?->toISOString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'state_history' => $this->whenLoaded('stateHistories', function () {
                return $this->stateHistories->map(function ($h) {
                    return [
                        'id' => $h->id,
                        'from_state' => $h->from_state?->value,
                        'to_state' => $h->to_state?->value,
                        'trigger' => $h->trigger,
                        'reason' => $h->reason,
                        'metadata' => $h->metadata,
                        'created_at' => $h->created_at?->toISOString(),
                    ];
                });
            }),
            'issuance_attempts' => $this->whenLoaded('issuanceAttempts', function () {
                return $this->issuanceAttempts->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'attempt_number' => $a->attempt_number,
                        'scenario' => $a->scenario,
                        'status' => $a->status,
                        'request_payload' => $a->request_payload,
                        'response_payload' => $a->response_payload,
                        'error_message' => $a->error_message,
                        'created_at' => $a->created_at?->toISOString(),
                    ];
                });
            }),
        ];
    }
}
