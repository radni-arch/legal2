<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentExecution extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'collaboration_id',
        'agent_name',
        'agent_role',
        'execution_order',
        'status',
        'task_description',
        'input_context',
        'output',
        'messages_to_others',
        'messages_from_others',
        'tokens_used',
        'cost_spent',
        'duration_ms',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input_context' => 'array',
        'output' => 'array',
        'messages_to_others' => 'array',
        'messages_from_others' => 'array',
        'tokens_used' => 'integer',
        'cost_spent' => 'decimal:4',
        'duration_ms' => 'integer',
        'execution_order' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the collaboration this execution belongs to
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(AgentCollaboration::class, 'collaboration_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    /**
     * Helper methods
     */
    public function markRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $output): void
    {
        $duration = $this->started_at ? (int) $this->started_at->diffInMilliseconds(now()) : 0;

        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
            'duration_ms' => $duration,
        ]);

        // Update collaboration step counter
        $this->collaboration->incrementStep();
    }

    public function markFailed(string $error): void
    {
        $duration = $this->started_at ? (int) $this->started_at->diffInMilliseconds(now()) : 0;

        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
            'duration_ms' => $duration,
        ]);
    }

    public function addTokensUsed(int $tokens): void
    {
        $this->increment('tokens_used', $tokens);

        // Rough cost calculation
        $cost = ($tokens / 1000000) * 0.15;
        $this->increment('cost_spent', $cost);

        // Also update collaboration total
        $this->collaboration->addTokensUsed($tokens);
    }

    public function sendMessageTo(string $targetAgent, array $message): void
    {
        $messages = $this->messages_to_others ?? [];
        $messages[] = [
            'to' => $targetAgent,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->messages_to_others = $messages;
        $this->save();
    }

    public function receiveMessageFrom(string $sourceAgent, array $message): void
    {
        $messages = $this->messages_from_others ?? [];
        $messages[] = [
            'from' => $sourceAgent,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->messages_from_others = $messages;
        $this->save();
    }
}
