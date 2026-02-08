<?php

namespace App\Listeners;

use App\Events\NewInsightDiscovered;
use App\Models\AgentInsightEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener for logging new insights to the database.
 *
 * This listener is queueable for better performance and will store
 * insight events in the agent_insight_events table for monitoring
 * and analytics purposes.
 */
class LogNewInsight implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'default';

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Handle the event.
     */
    public function handle(NewInsightDiscovered $event): void
    {
        try {
            // Only log insights that exceed the relevance threshold
            if (! $event->exceedsThreshold()) {
                Log::debug('Insight below threshold, skipping logging', [
                    'agent_name' => $event->agentName,
                    'run_id' => $event->run->id,
                    'relevance_score' => $event->relevanceScore,
                ]);

                return;
            }

            // Create insight event record
            AgentInsightEvent::create([
                'agent_name' => $event->agentName,
                'agent_run_id' => $event->run->id,
                'insight' => $event->insight,
                'objective' => $event->run->objective,
                'severity' => $event->severity,
                'relevance_score' => $event->relevanceScore,
                'metadata' => $event->metadata,
                'source' => $event->metadata['source'] ?? 'autonomous_research',
                'source_id' => $event->metadata['source_id'] ?? (string) $event->run->id,
            ]);

            Log::info('New insight logged', [
                'agent_name' => $event->agentName,
                'run_id' => $event->run->id,
                'objective' => $event->run->objective,
                'severity' => $event->severity,
                'relevance_score' => $event->relevanceScore,
                'insight_preview' => substr($event->insight, 0, 100),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log insight event', [
                'agent_name' => $event->agentName,
                'run_id' => $event->run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Determine if the listener should be queued.
     */
    public function shouldQueue(NewInsightDiscovered $event): bool
    {
        // Only queue if the insight exceeds the threshold
        return $event->exceedsThreshold();
    }

    /**
     * Handle a job failure.
     */
    public function failed(NewInsightDiscovered $event, \Throwable $exception): void
    {
        Log::error('Failed to log insight after all retries', [
            'agent_name' => $event->agentName,
            'run_id' => $event->run->id,
            'error' => $exception->getMessage(),
            'insight_preview' => substr($event->insight, 0, 100),
        ]);
    }
}
