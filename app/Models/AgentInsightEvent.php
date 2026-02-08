<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AgentInsightEvent model
 *
 * Stores events for significant insights discovered during agent research runs.
 * Used for monitoring, analytics, and event-driven workflows.
 *
 * @property int $id
 * @property string $agent_name
 * @property int|null $agent_run_id
 * @property string $insight
 * @property string|null $objective
 * @property string $severity
 * @property float|null $relevance_score
 * @property array|null $metadata
 * @property string|null $source
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AgentRun|null $run
 */
class AgentInsightEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_name',
        'agent_run_id',
        'insight',
        'objective',
        'severity',
        'relevance_score',
        'metadata',
        'source',
        'source_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'relevance_score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the agent run that this event belongs to
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /**
     * Scope query to recent events
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope query to specific severity
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope query to specific agent
     */
    public function scopeForAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    /**
     * Scope query to specific objective
     */
    public function scopeForObjective($query, string $objective)
    {
        return $query->where('objective', 'LIKE', '%'.$objective.'%');
    }
}
