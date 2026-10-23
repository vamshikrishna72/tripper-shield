<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'provider_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'policy_request_id',
        'provider_reference',
        'event_type',
        'payload',
        'processed',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
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
