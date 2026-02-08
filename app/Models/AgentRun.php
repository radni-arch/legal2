<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_name',
        'job_id',
        'queue',
        'objective',
        'context',
        'topics',
        'iterations',
        'checkpoint_state',
        'last_checkpoint_at',
        'can_resume',
        'status',
        'current_iteration',
        'score',
        'max_iterations',
        'threshold',
        'token_budget',
        'tokens_used',
        'cost_budget',
        'cost_spent',
        'time_limit_seconds',
        'started_at',
        'completed_at',
        'elapsed_seconds',
        'final_output',
        'error',
    ];

    protected $casts = [
        'context' => 'array',
        'topics' => 'array',
        'iterations' => 'array',
        'checkpoint_state' => 'array',

        // final_output is rendered as Markdown in views and returned as a string in APIs.
        // Casting it to array causes json_decode() on a markdown string, which becomes null.
        'final_output' => 'string',

        'last_checkpoint_at' => 'datetime',
        'can_resume' => 'boolean',
        'score' => 'float',
        'threshold' => 'float',
        'token_budget' => 'float',
        'tokens_used' => 'float',
        'cost_budget' => 'float',
        'cost_spent' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Check if this run can be resumed
     */
    public function canBeResumed(): bool
    {
        return $this->can_resume
            && $this->status === 'paused'
            && $this->checkpoint_state !== null;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->max_iterations === 0) {
            return 0;
        }

        return round(($this->current_iteration / $this->max_iterations) * 100, 1);
    }

    /**
     * Scope: Get runs from the last N days
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get the user who started this agent run
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
