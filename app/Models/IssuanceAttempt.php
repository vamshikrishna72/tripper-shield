<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuanceAttempt extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'issuance_attempts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'policy_request_id',
        'attempt_number',
        'scenario',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    /**
     * Associated policy request.
     */
    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class, 'policy_request_id');
    }
}
