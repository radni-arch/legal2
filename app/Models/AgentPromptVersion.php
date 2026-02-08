<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores versioned prompt configurations for legal-artillery and other agents.
 *
 * Each agent_name can have multiple versions, but only one is_active at a time.
 * Integrates with git versioning — stores the git commit hash in metadata.
 *
 * @property int $id
 * @property string $agent_name
 * @property string $version
 * @property string $instructions
 * @property array|null $metadata
 * @property bool $is_active
 * @property array|null $performance_metrics
 */
class AgentPromptVersion extends Model
{
    protected $fillable = [
        'agent_name',
        'version',
        'instructions',
        'metadata',
        'is_active',
        'performance_metrics',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'performance_metrics' => 'array',
    ];

    /**
     * Get the active prompt version for a given agent.
     */
    public static function activeFor(string $agentName): ?self
    {
        return static::where('agent_name', $agentName)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a new version and deactivate all previous versions.
     */
    public static function createVersion(
        string $agentName,
        string $version,
        string $instructions,
        array $metadata = [],
        bool $activate = true,
    ): self {
        if ($activate) {
            static::where('agent_name', $agentName)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return static::create([
            'agent_name' => $agentName,
            'version' => $version,
            'instructions' => $instructions,
            'metadata' => $metadata,
            'is_active' => $activate,
        ]);
    }

    /**
     * Get all versions for a given agent, ordered by creation date desc.
     */
    public static function historyFor(string $agentName): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('agent_name', $agentName)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Record performance metrics for this version.
     */
    public function recordMetrics(array $metrics): self
    {
        $this->update([
            'performance_metrics' => array_merge(
                $this->performance_metrics ?? [],
                $metrics,
            ),
        ]);

        return $this;
    }
}
