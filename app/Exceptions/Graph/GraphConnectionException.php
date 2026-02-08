<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;
use Throwable;

/**
 * Exception thrown when Neo4j graph database connection fails.
 *
 * This exception provides context about the connection failure including
 * the host and connection configuration details.
 */
class GraphConnectionException extends GraphException
{
    /**
     * The Neo4j host/URI that failed to connect
     */
    protected ?string $host = null;

    /**
     * The full connection configuration
     *
     * @var array<string, mixed>|null
     */
    protected ?array $connectionConfig = null;

    /**
     * Set the host context
     */
    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Get the host context
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Set the connection configuration context
     *
     * @param array<string, mixed> $config
     */
    public function setConnectionConfig(array $config): self
    {
        $this->connectionConfig = $config;

        // Extract host from config if present
        if (isset($config['host']) && is_string($config['host'])) {
            $this->host = $config['host'];
        }

        return $this;
    }

    /**
     * Get the connection configuration context
     *
     * @return array<string, mixed>|null
     */
    public function getConnectionConfig(): ?array
    {
        return $this->connectionConfig;
    }

    /**
     * Get all context as an array
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [
            'host' => $this->host,
            'connection_config' => $this->connectionConfig,
        ];
    }

    /**
     * Create exception for a host connection failure
     *
     * @param string $host The Neo4j host/URI that failed
     * @param Throwable|null $previous The previous exception, if any
     * @return static
     */
    public static function forHost(string $host, ?Throwable $previous = null): static
    {
        $message = sprintf(
            'Graph database connection failed to Neo4j at %s',
            $host
        );

        $exception = new static($message, 0, $previous);
        $exception->setHost($host);

        return $exception;
    }

    /**
     * Create exception for a connection configuration failure
     *
     * @param array<string, mixed> $config The connection configuration
     * @param Throwable|null $previous The previous exception, if any
     * @return static
     */
    public static function forConnection(array $config, ?Throwable $previous = null): static
    {
        $host = $config['host'] ?? 'unknown';

        $message = sprintf(
            'Graph database connection failed to Neo4j at %s',
            $host
        );

        $exception = new static($message, 0, $previous);
        $exception->setConnectionConfig($config);

        return $exception;
    }
}
