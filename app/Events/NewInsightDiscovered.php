<?php

namespace App\Events;

use App\Models\AgentRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a new insight exceeding the relevance threshold is discovered.
 *
 * This event enables automated monitoring and logging of significant insights
 * discovered during autonomous agent research runs.
 */
class NewInsightDiscovered
{
    use Dispatchable, SerializesModels;

    /**
     * The insight content
     */
    public string $insight;

    /**
     * The agent run that discovered this insight
     */
    public AgentRun $run;

    /**
     * The agent name
     */
    public string $agentName;

    /**
     * Metadata about the insight
     */
    public array $metadata;

    /**
     * Relevance score (0-1) if available
     */
    public ?float $relevanceScore;

    /**
     * Severity level for logging (info, warning, critical)
     */
    public string $severity;

    /**
     * Create a new event instance.
     *
     * @param  string  $insight  The insight content
     * @param  AgentRun  $run  The agent run
     * @param  string  $agentName  The agent name
     * @param  array  $metadata  Additional metadata
     * @param  float|null  $relevanceScore  Relevance score (0-1)
     * @param  string  $severity  Severity level (info, warning, critical)
     */
    public function __construct(
        string $insight,
        AgentRun $run,
        string $agentName,
        array $metadata = [],
        ?float $relevanceScore = null,
        string $severity = 'info'
    ) {
        $this->insight = $insight;
        $this->run = $run;
        $this->agentName = $agentName;
        $this->metadata = $metadata;
        $this->relevanceScore = $relevanceScore;
        $this->severity = $severity;
    }

    /**
     * Determine if the insight exceeds the relevance threshold
     *
     * @param  float  $threshold  Default threshold is 0.7
     */
    public function exceedsThreshold(float $threshold = 0.7): bool
    {
        if ($this->relevanceScore === null) {
            // If no relevance score, consider all insights as exceeding threshold
            return true;
        }

        return $this->relevanceScore >= $threshold;
    }
}
