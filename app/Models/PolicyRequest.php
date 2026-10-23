<?php

namespace App\Models;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\PolicyStatus;
use App\Enums\SumInsured;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PolicyRequest extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'policy_requests';

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'customer_age',
        'premium_amount',
        'currency',
        'calculation_breakdown',
        'status',
        'provider_reference',
        'policy_number',
        'idempotency_key',
        'attempt_count',
        'last_attempted_at',
        'issued_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'plan_code' => PlanCode::class,
            'destination' => Destination::class,
            'sum_insured' => 'integer',
            'date_of_birth' => 'date:Y-m-d',
            'trip_start' => 'date:Y-m-d',
            'trip_end' => 'date:Y-m-d',
            'travel_days' => 'integer',
            'customer_age' => 'integer',
            'premium_amount' => 'decimal:2',
            'calculation_breakdown' => 'array',
            'attempt_count' => 'integer',
            'last_attempted_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * State transition history entries.
     */
    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class, 'policy_request_id')->orderBy('created_at', 'asc');
    }

    /**
     * Provider issuance attempts.
     */
    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class, 'policy_request_id')->orderBy('attempt_number', 'asc');
    }

    /**
     * Inbound provider events for this policy request.
     */
    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class, 'policy_request_id')->orderBy('created_at', 'asc');
    }

    /**
     * Check if policy is already successfully issued.
     */
    public function isIssued(): bool
    {
        return $this->status === PolicyStatus::ISSUED;
    }

    /**
     * Generate stable provider idempotency key derived from the internal policy request ID.
     */
    public static function buildIdempotencyKey(string $id): string
    {
        return 'idem_' . hash('sha256', "trippershield:policy:{$id}");
    }
}
