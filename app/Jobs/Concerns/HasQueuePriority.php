<?php

namespace App\Jobs\Concerns;

/**
 * Trait HasQueuePriority
 *
 * Provides queue priority support for jobs.
 * Use this trait in job classes to easily set queue priority.
 *
 * Queue Priority Order (as configured in Supervisor):
 * 1. high     - Critical operations (payment processing, user actions, real-time notifications)
 * 2. agents   - AI agent jobs (research, decision discovery, autonomous agents)
 * 3. textract - AWS Textract OCR processing (document analysis)
 * 4. default  - Standard jobs (email sending, data processing)
 * 5. low      - Background maintenance (cleanup, statistics, cache warming)
 *
 * Usage:
 *
 * class MyJob implements ShouldQueue
 * {
 *     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasQueuePriority;
 *
 *     public function __construct()
 *     {
 *         $this->onHighPriorityQueue(); // Or onAgentsQueue(), onTextractQueue(), etc.
 *     }
 * }
 */
trait HasQueuePriority
{
    /**
     * Set job to high priority queue.
     * Use for critical operations that must be processed immediately.
     *
     * Examples:
     * - Payment processing
     * - User-triggered actions
     * - Real-time notifications
     * - Security alerts
     */
    public function onHighPriorityQueue(): self
    {
        $this->onQueue('high');

        return $this;
    }

    /**
     * Set job to agents queue.
     * Use for AI agent jobs and autonomous operations.
     *
     * Examples:
     * - Autonomous research agents
     * - Decision discovery agents
     * - Court decision analysis
     * - Legal research
     */
    public function onAgentsQueue(): self
    {
        $this->onQueue('agents');

        return $this;
    }

    /**
     * Set job to textract queue.
     * Use for AWS Textract OCR processing.
     *
     * Examples:
     * - Document OCR processing
     * - PDF text extraction
     * - Image text extraction
     * - Table extraction
     */
    public function onTextractQueue(): self
    {
        $this->onQueue('textract');

        return $this;
    }

    /**
     * Set job to default queue.
     * Use for standard background jobs.
     *
     * Examples:
     * - Email sending
     * - Data synchronization
     * - Report generation
     * - File uploads
     */
    public function onDefaultQueue(): self
    {
        $this->onQueue('default');

        return $this;
    }

    /**
     * Set job to low priority queue.
     * Use for non-urgent maintenance and cleanup tasks.
     *
     * Examples:
     * - Cache warming
     * - Database cleanup
     * - Statistics calculation
     * - Old file deletion
     * - Log rotation
     */
    public function onLowPriorityQueue(): self
    {
        $this->onQueue('low');

        return $this;
    }

    /**
     * Get recommended queue priority for job type.
     *
     * @param  string  $type  Job type: 'critical', 'agent', 'ocr', 'standard', 'maintenance'
     * @return string Queue name
     */
    public static function getQueueForType(string $type): string
    {
        return match ($type) {
            'critical' => 'high',
            'agent' => 'agents',
            'ocr', 'textract' => 'textract',
            'standard' => 'default',
            'maintenance', 'cleanup' => 'low',
            default => 'default',
        };
    }
}
