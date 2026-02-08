<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrchestrationLog extends Model
{
    protected $fillable = [
        'orchestration_id',
        'task_description',
        'agent_pipeline',
        'shared_context',
        'execution_history',
        'status',
        'total_agents',
        'completed_agents',
        'failed_agents',
        'tokens_used',
        'cost_spent',
        'duration_ms',
        'token_budget',
        'cost_budget',
        'time_budget_ms',
        'error_message',
        'started_at',
        'completed_at',
        'feedback_requests',
        'feedback_iteration_count',
    ];

    protected $casts = [
        'agent_pipeline' => 'array',
        'shared_context' => 'array',
        'execution_history' => 'array',
        'total_agents' => 'integer',
        'completed_agents' => 'integer',
        'failed_agents' => 'integer',
        'tokens_used' => 'integer',
        'cost_spent' => 'decimal:4',
        'duration_ms' => 'integer',
        'token_budget' => 'integer',
        'cost_budget' => 'decimal:4',
        'time_budget_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'feedback_requests' => 'array',
        'feedback_iteration_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->orchestration_id)) {
                $model->orchestration_id = (string) Str::uuid();
            }
        });
    }
}
