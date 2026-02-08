<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI Reasoning Trace Model
 *
 * Stores AI agent reasoning traces for transparency, auditing, and debugging.
 * Supports nested traces via parent_trace_id for hierarchical reasoning chains.
 *
 * Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 *
 * @property int $id
 * @property string $trace_id UUID
 * @property string|null $parent_trace_id
 * @property string|null $collaboration_id
 * @property string|null $agent_type
 * @property string|null $step_type
 * @property string|null $operation
 * @property array|null $input_data
 * @property array|null $output_data
 * @property string|null $reasoning
 * @property float|null $confidence
 * @property int|null $tokens_used
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AiReasoningTrace extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_reasoning_traces';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'trace_id',
        'parent_trace_id',
        'collaboration_id',
        'agent_type',
        'step_type',
        'operation',
        'input_data',
        'output_data',
        'reasoning',
        'confidence',
        'tokens_used',
        'duration_ms',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'confidence' => 'float',
        'tokens_used' => 'integer',
        'duration_ms' => 'integer',
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
     * Automatically generate UUID for trace_id on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->trace_id)) {
                $model->trace_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the parent trace that this trace is nested under.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(AiReasoningTrace::class, 'parent_trace_id', 'trace_id');
    }

    /**
     * Get the child traces nested under this trace.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(AiReasoningTrace::class, 'parent_trace_id', 'trace_id');
    }

    /**
     * Get all descendant traces recursively.
     *
     * This uses a recursive CTE query to fetch the entire trace tree.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDescendantsTree()
    {
        return self::getTraceTree($this->trace_id);
    }

    /**
     * Get the full trace tree starting from a given trace_id.
     *
     * Uses recursive CTE (Common Table Expression) to fetch nested traces.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getTraceTree(string $traceId)
    {
        // Recursive CTE query to get full trace tree
        $query = '
            WITH RECURSIVE trace_tree AS (
                -- Base case: start with the given trace
                SELECT *
                FROM ai_reasoning_traces
                WHERE trace_id = :trace_id

                UNION ALL

                -- Recursive case: get children of current level
                SELECT art.*
                FROM ai_reasoning_traces art
                INNER JOIN trace_tree tt ON art.parent_trace_id = tt.trace_id
            )
            SELECT * FROM trace_tree
            ORDER BY created_at ASC
        ';

        $results = \DB::select($query, ['trace_id' => $traceId]);

        return collect($results)->map(function ($row) {
            return (array) $row;
        });
    }
}
