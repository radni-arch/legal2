<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentCollaboration extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'fact_pattern_id',
        'session_id',
        'orchestrator',
        'problem_type',
        'problem_statement',
        'context',
        'status',
        'agents_involved',
        'execution_plan',
        'shared_memory',
        'agent_outputs',
        'final_result',
        'synthesis',
        'total_steps',
        'completed_steps',
        'tokens_used',
        'cost_spent',
        'duration_seconds',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'agents_involved' => 'array',
        'execution_plan' => 'array',
        'shared_memory' => 'array',
        'agent_outputs' => 'array',
        'final_result' => 'array',
        'synthesis' => 'array',
        'tokens_used' => 'integer',
        'cost_spent' => 'decimal:4',
        'duration_seconds' => 'integer',
        'total_steps' => 'integer',
        'completed_steps' => 'integer',
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
            if (empty($model->session_id)) {
                $model->session_id = 'collab_'.Str::random(16);
            }
        });
    }

    /**
     * Get all executions for this collaboration
     */
    public function executions(): HasMany
    {
        return $this->hasMany(AgentExecution::class, 'collaboration_id')
            ->orderBy('execution_order');
    }

    /**
     * Get completed executions
     */
    public function completedExecutions(): HasMany
    {
        return $this->hasMany(AgentExecution::class, 'collaboration_id')
            ->where('status', 'completed')
            ->orderBy('execution_order');
    }

    /**
     * Scopes
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /**
     * Helper methods
     */
    public function addToSharedMemory(string $key, $value): void
    {
        $memory = $this->shared_memory ?? [];
        $memory[$key] = $value;
        $this->shared_memory = $memory;
        $this->save();
    }

    public function getFromSharedMemory(string $key, $default = null)
    {
        return $this->shared_memory[$key] ?? $default;
    }

    public function markCompleted(array $finalResult, ?string $synthesis = null): void
    {
        $duration = $this->started_at ? (int) $this->started_at->diffInSeconds(now()) : 0;

        $this->update([
            'status' => 'completed',
            'final_result' => $finalResult,
            'synthesis' => $synthesis,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    public function markFailed(string $error): void
    {
        $duration = $this->started_at ? (int) $this->started_at->diffInSeconds(now()) : 0;

        $this->update([
            'status' => 'failed',
            'synthesis' => "Collaboration failed: {$error}",
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    public function incrementStep(): void
    {
        $this->increment('completed_steps');
    }

    public function addTokensUsed(int $tokens): void
    {
        $this->increment('tokens_used', $tokens);

        // Rough cost calculation for GPT-4o-mini: $0.15 per 1M tokens
        $cost = ($tokens / 1000000) * 0.15;
        $this->increment('cost_spent', $cost);
    }

    /**
     * Get progress percentage
     */
    public function getProgressAttribute(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->completed_steps / $this->total_steps) * 100, 1);
    }

    /**
     * Accessor for agent_results (alias for agent_outputs)
     */
    public function getAgentResultsAttribute(): ?array
    {
        return $this->agent_outputs;
    }

    /**
     * Mutator for agent_results (alias for agent_outputs)
     */
    public function setAgentResultsAttribute($value): void
    {
        $this->agent_outputs = $value;
    }
}
