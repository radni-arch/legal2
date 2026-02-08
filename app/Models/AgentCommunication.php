<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Agent Communication Model
 *
 * Tracks inter-agent communications during collaboration sessions.
 * Stores message passing, requests, responses, and communication status.
 *
 * Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 *
 * @property int $id
 * @property string $communication_id UUID
 * @property string|null $trace_id
 * @property string|null $collaboration_id
 * @property string|null $sender_agent_type
 * @property string|null $receiver_agent_type
 * @property string|null $message_type
 * @property array|null $message_data
 * @property array|null $response_data
 * @property string|null $status
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AgentCommunication extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'agent_communications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'communication_id',
        'trace_id',
        'collaboration_id',
        'sender_agent_type',
        'receiver_agent_type',
        'message_type',
        'message_data',
        'response_data',
        'status',
        'duration_ms',
        'priority',
        'retry_count',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'message_data' => 'array',
        'response_data' => 'array',
        'duration_ms' => 'integer',
        'priority' => 'integer',
        'retry_count' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * Boot function from Laravel.
     *
     * Automatically generate UUID for communication_id on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->communication_id)) {
                $model->communication_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the reasoning trace associated with this communication.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function trace()
    {
        return $this->belongsTo(AiReasoningTrace::class, 'trace_id', 'trace_id');
    }

    /**
     * Scope query to communications from a specific agent type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromAgent($query, string $agentType)
    {
        return $query->where('sender_agent_type', $agentType);
    }

    /**
     * Scope query to communications to a specific agent type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeToAgent($query, string $agentType)
    {
        return $query->where('receiver_agent_type', $agentType);
    }

    /**
     * Scope query to communications with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
