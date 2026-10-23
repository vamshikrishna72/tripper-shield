<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyStateHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'policy_state_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'policy_request_id',
        'from_state',
        'to_state',
        'trigger',
        'reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_state' => PolicyStatus::class,
            'to_state' => PolicyStatus::class,
            'metadata' => 'array',
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
