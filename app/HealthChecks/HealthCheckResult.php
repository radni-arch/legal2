<?php

namespace App\HealthChecks;

/**
 * Value object representing the result of a health check.
 */
class HealthCheckResult
{
    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $data = []
    ) {}

    /**
     * Create a healthy status result.
     */
    public static function healthy(array $data = [], string $message = 'Service is healthy'): self
    {
        return new self('healthy', $message, $data);
    }

    /**
     * Create a degraded status result.
     */
    public static function degraded(string $message, array $data = []): self
    {
        return new self('degraded', $message, $data);
    }

    /**
     * Create an unhealthy status result.
     */
    public static function unhealthy(string $message, array $data = []): self
    {
        return new self('unhealthy', $message, $data);
    }

    /**
     * Convert result to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if the health check passed.
     */
    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    /**
     * Check if the health check is degraded.
     */
    public function isDegraded(): bool
    {
        return $this->status === 'degraded';
    }

    /**
     * Check if the health check failed.
     */
    public function isUnhealthy(): bool
    {
        return $this->status === 'unhealthy';
    }
}
