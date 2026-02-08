<?php

namespace App\Traits;

use App\Logging\CorrelationIdProcessor;
use Illuminate\Support\Facades\Log;

/**
 * Trait for structured logging in AI agents
 *
 * Provides consistent, structured logging methods for all agents with
 * automatic correlation ID tracking, agent context, and metrics.
 *
 * Sprint 1.7: Observability Setup
 *
 * Usage:
 *   use StructuredAgentLogging;
 *
 *   $this->logAgentStart('research', ['objective' => $objective]);
 *   $this->logAgentSuccess('research', ['results_count' => 10], $duration);
 *   $this->logAgentFailure('research', $exception, $duration);
 */
trait StructuredAgentLogging
{
    /**
     * Get the agent name for logging
     */
    protected function getAgentName(): string
    {
        return property_exists($this, 'name')
            ? $this->name
            : class_basename($this);
    }

    /**
     * Get correlation ID for current execution
     */
    protected function getCorrelationId(): string
    {
        return CorrelationIdProcessor::getCorrelationId();
    }

    /**
     * Log agent execution start
     *
     * @param  string  $operation  Operation name (e.g., 'research', 'analyze', 'evaluate')
     * @param  array  $context  Additional context (objective, run_id, etc.)
     * @return float Start timestamp for duration calculation
     */
    protected function logAgentStart(string $operation, array $context = []): float
    {
        $startTime = microtime(true);

        Log::channel('agents')->info("Agent {$operation} started", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $context));

        return $startTime;
    }

    /**
     * Log agent execution success
     *
     * @param  string  $operation  Operation name
     * @param  array  $context  Additional context (results, metrics, etc.)
     * @param  float|null  $startTime  Start timestamp from logAgentStart()
     */
    protected function logAgentSuccess(string $operation, array $context = [], ?float $startTime = null): void
    {
        $duration = $startTime ? (microtime(true) - $startTime) * 1000 : null;

        Log::channel('agents')->info("Agent {$operation} completed successfully", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'status' => 'success',
            'correlation_id' => $this->getCorrelationId(),
            'duration_ms' => $duration ? round($duration, 2) : null,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log agent execution failure
     *
     * @param  string  $operation  Operation name
     * @param  \Throwable  $exception  Exception that caused failure
     * @param  float|null  $startTime  Start timestamp from logAgentStart()
     * @param  array  $context  Additional context
     */
    protected function logAgentFailure(string $operation, \Throwable $exception, ?float $startTime = null, array $context = []): void
    {
        $duration = $startTime ? (microtime(true) - $startTime) * 1000 : null;

        Log::channel('agents')->error("Agent {$operation} failed", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'status' => 'failure',
            'correlation_id' => $this->getCorrelationId(),
            'duration_ms' => $duration ? round($duration, 2) : null,
            'error' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_class' => get_class($exception),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log agent iteration/step
     *
     * @param  string  $operation  Operation name
     * @param  int  $iteration  Current iteration number
     * @param  array  $context  Iteration context (score, actions, etc.)
     */
    protected function logAgentIteration(string $operation, int $iteration, array $context = []): void
    {
        Log::channel('agents')->info("Agent {$operation} iteration {$iteration}", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'iteration' => $iteration,
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log agent metrics/telemetry
     *
     * @param  string  $operation  Operation name
     * @param  array  $metrics  Metrics to log (tokens_used, cost, score, etc.)
     */
    protected function logAgentMetrics(string $operation, array $metrics): void
    {
        Log::channel('agents')->info("Agent {$operation} metrics", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'event_type' => 'metrics',
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $metrics));
    }

    /**
     * Log agent warning
     *
     * @param  string  $operation  Operation name
     * @param  string  $message  Warning message
     * @param  array  $context  Additional context
     */
    protected function logAgentWarning(string $operation, string $message, array $context = []): void
    {
        Log::channel('agents')->warning("Agent {$operation}: {$message}", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log agent debug information
     *
     * @param  string  $operation  Operation name
     * @param  string  $message  Debug message
     * @param  array  $context  Additional context
     */
    protected function logAgentDebug(string $operation, string $message, array $context = []): void
    {
        Log::channel('agents')->debug("Agent {$operation}: {$message}", array_merge([
            'agent_name' => $this->getAgentName(),
            'operation' => $operation,
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Create a child correlation ID for nested operations
     *
     * Useful for tracking sub-operations within an agent execution
     *
     * @param  string  $suffix  Suffix to append (e.g., 'iteration-1', 'sub-agent')
     * @return string Child correlation ID
     */
    protected function createChildCorrelationId(string $suffix): string
    {
        $parentId = $this->getCorrelationId();

        return "{$parentId}:{$suffix}";
    }

    /**
     * Log with custom channel
     *
     * @param  string  $channel  Log channel name
     * @param  string  $level  Log level (info, warning, error, debug)
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logToChannel(string $channel, string $level, string $message, array $context = []): void
    {
        Log::channel($channel)->$level($message, array_merge([
            'agent_name' => $this->getAgentName(),
            'correlation_id' => $this->getCorrelationId(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }
}
