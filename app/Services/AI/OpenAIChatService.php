<?php

namespace App\Services\AI;

use App\Contracts\AI\CacheServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Services\CircuitBreaker;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * OpenAI Chat Service
 *
 * Extracted from OpenAIService.php to handle chat/completion operations.
 * Implements ChatServiceInterface for dependency injection.
 *
 * Responsibilities:
 * - Chat completions (basic, streaming)
 * - Request/response caching
 * - Error handling and retry logic
 * - Circuit breaker integration
 * - Request/response logging
 *
 * @see \App\Contracts\AI\ChatServiceInterface
 */
class OpenAIChatService implements ChatServiceInterface
{
    protected string $apiKey;

    protected ?string $organization;

    protected ?string $project;

    protected string $baseUrl;

    protected int $timeout;

    protected int $connectTimeout;

    protected CircuitBreaker $circuitBreaker;

    protected int $cacheTtl = 3600; // 1 hour default cache TTL

    /**
     * Create a new OpenAIChatService instance
     *
     * @param  CacheServiceInterface  $cache  Cache service for response caching
     */
    public function __construct(
        protected CacheServiceInterface $cache
    ) {
        $this->apiKey = (string) config('openai.api_key');
        $this->organization = config('openai.organization');
        $this->project = config('openai.project');
        $this->baseUrl = rtrim((string) config('openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->timeout = (int) config('openai.timeout', 60);
        $this->connectTimeout = (int) config('openai.connect_timeout', 10);

        // Initialize circuit breaker for OpenAI API
        $this->circuitBreaker = new CircuitBreaker(
            serviceName: 'openai-chat',
            failureThreshold: config('openai.circuit_breaker.failure_threshold', 5),
            successThreshold: config('openai.circuit_breaker.success_threshold', 2),
            timeout: config('openai.circuit_breaker.timeout', 60),
            retryAfter: config('openai.circuit_breaker.retry_after', 30)
        );
    }

    /**
     * Send a chat completion request
     *
     * @param  array  $messages  Chat messages (role, content)
     * @param  string  $model  Model name (gpt-4o, gpt-4o-mini, etc.)
     * @param  array  $options  Additional options (temperature, max_tokens, etc.)
     * @return array Response with choices, usage, etc.
     *
     * @throws Throwable
     */
    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array
    {
        // Check cache first
        $cacheKey = $this->cache->generateKey('chat', [
            'messages' => $messages,
            'model' => $model,
            'options' => $options,
        ]);

        $cachedResponse = $this->cache->get($cacheKey);
        if ($cachedResponse !== null) {
            return array_merge($cachedResponse, ['cached' => true]);
        }

        // Build payload
        $payload = array_merge($options, [
            'model' => $model ?: config('openai.models.chat'),
            'messages' => $messages,
        ]);

        // Make request
        try {
            $response = $this->request('POST', '/chat/completions', $payload);

            // Cache successful response
            $this->cache->put($cacheKey, $response, $this->cacheTtl);

            return $response;
        } catch (Throwable $e) {
            // Don't cache errors
            throw $e;
        }
    }

    /**
     * Send a chat completion request with streaming
     *
     * Streams OpenAI chat completions using Server-Sent Events (SSE).
     * Calls the callback function for each streamed chunk.
     *
     * Protected by circuit breaker to prevent cascading failures.
     * Tracks chunk-level successes and failures.
     *
     * @param  array  $messages  Chat messages (role, content)
     * @param  string  $model  Model name (gpt-4o, gpt-4o-mini, etc.)
     * @param  array  $options  Additional options (temperature, max_tokens, etc.)
     * @param  callable  $callback  Callback function for each chunk: function(array $chunk): void
     *
     * @throws \App\Exceptions\OpenAIException
     * @throws \App\Exceptions\PartialOpenAIFailureException
     * @throws \App\Services\CircuitBreakerException
     * @throws Throwable
     */
    public function chatStream(array $messages, string $model, array $options, callable $callback): void
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);

        // Check circuit breaker before starting stream
        if (! $this->circuitBreaker->getStatus()['can_retry']) {
            $this->logChannel()->warning('openai.stream.circuit_open', [
                'event' => 'openai.stream.circuit_open',
                'request_id' => $reqId,
                'circuit_state' => $this->circuitBreaker->getStatus()['state'],
            ]);

            throw new \App\Services\CircuitBreakerException(
                'Circuit breaker is OPEN for service: openai-chat. Streaming is currently unavailable.',
                \App\Services\CircuitBreakerException::CIRCUIT_OPEN
            );
        }

        // Build payload with stream option
        $payload = array_merge($options, [
            'model' => $model ?: config('openai.models.chat'),
            'messages' => $messages,
            'stream' => true,
        ]);

        $this->logChannel()->info('openai.stream.request', [
            'event' => 'openai.stream.request',
            'request_id' => $reqId,
            'model' => $payload['model'],
            'message_count' => count($messages),
        ]);

        $chunkCount = 0;
        $successfulChunks = [];
        $streamingStarted = false;

        try {
            // Make streaming request - wrapped in circuit breaker
            $response = $this->circuitBreaker->call(function () use ($payload) {
                return $this->client()
                    ->timeout($this->timeout)
                    ->withOptions(['stream' => true])
                    ->post('/chat/completions', $payload);
            });

            if (! $response->successful()) {
                throw new \App\Exceptions\OpenAIException(
                    "OpenAI streaming request failed: {$response->status()}",
                    \App\Exceptions\OpenAIException::STREAMING_FAILED
                );
            }

            // Stream started successfully
            $streamingStarted = true;

            // Read streaming response
            $body = $response->body();
            $lines = explode("\n", $body);

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines and comments
                if ($line === '' || str_starts_with($line, ':')) {
                    continue;
                }

                // Parse SSE data line
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);

                    // Check for stream end
                    if ($data === '[DONE]') {
                        break;
                    }

                    // Parse JSON chunk
                    try {
                        $chunk = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

                        // Call callback with chunk
                        $callback($chunk);

                        // Track successful chunk
                        $successfulChunks[] = $chunk;
                        $chunkCount++;

                        // Record success in circuit breaker for each chunk
                        // This helps track streaming health
                        if ($chunkCount % 10 === 0) {
                            // Record success every 10 chunks to avoid excessive cache operations
                            $this->circuitBreaker->call(fn () => true);
                        }

                    } catch (\JsonException $e) {
                        $this->logChannel()->warning('openai.stream.parse_error', [
                            'event' => 'openai.stream.parse_error',
                            'request_id' => $reqId,
                            'error' => $e->getMessage(),
                            'data' => $data,
                            'chunk_number' => $chunkCount,
                        ]);

                        // Parse errors are not circuit breaker failures
                        // They're data format issues, not service availability issues
                    }
                }
            }

            $duration = (int) round((microtime(true) - $start) * 1000);

            // Record final success in circuit breaker
            $this->circuitBreaker->call(fn () => true);

            $this->logChannel()->info('openai.stream.complete', [
                'event' => 'openai.stream.complete',
                'request_id' => $reqId,
                'chunks_received' => $chunkCount,
                'duration_ms' => $duration,
            ]);

        } catch (\App\Services\CircuitBreakerException $e) {
            // Circuit breaker exception - re-throw as-is
            throw $e;
        } catch (\App\Exceptions\OpenAIException $e) {
            // OpenAI exception - check if we got partial results
            if ($streamingStarted && $chunkCount > 0) {
                // Partial stream failure - we got some chunks
                $duration = (int) round((microtime(true) - $start) * 1000);

                $this->logChannel()->error('openai.stream.partial_failure', [
                    'event' => 'openai.stream.partial_failure',
                    'request_id' => $reqId,
                    'chunks_received' => $chunkCount,
                    'duration_ms' => $duration,
                    'error' => [
                        'message' => $e->getMessage(),
                        'class' => get_class($e),
                    ],
                ]);

                throw new \App\Exceptions\PartialOpenAIFailureException(
                    "OpenAI streaming partially completed ({$chunkCount} chunks received): {$e->getMessage()}",
                    \App\Exceptions\PartialOpenAIFailureException::PARTIAL_STREAM_FAILURE,
                    $successfulChunks,
                    [
                        'chunks_received' => $chunkCount,
                        'streaming_started' => $streamingStarted,
                        'duration_ms' => $duration,
                    ],
                    $e
                );
            }

            // Complete failure - no chunks received
            throw $e;
        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            // Check if we got partial results
            if ($streamingStarted && $chunkCount > 0) {
                $this->logChannel()->error('openai.stream.partial_failure', [
                    'event' => 'openai.stream.partial_failure',
                    'request_id' => $reqId,
                    'chunks_received' => $chunkCount,
                    'duration_ms' => $duration,
                    'error' => [
                        'message' => $e->getMessage(),
                        'class' => get_class($e),
                    ],
                ]);

                throw new \App\Exceptions\PartialOpenAIFailureException(
                    "OpenAI streaming partially completed ({$chunkCount} chunks received): {$e->getMessage()}",
                    \App\Exceptions\PartialOpenAIFailureException::PARTIAL_STREAM_FAILURE,
                    $successfulChunks,
                    [
                        'chunks_received' => $chunkCount,
                        'streaming_started' => $streamingStarted,
                        'duration_ms' => $duration,
                    ],
                    $e
                );
            }

            $this->logChannel()->error('openai.stream.error', [
                'event' => 'openai.stream.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                ],
            ]);

            throw new \App\Exceptions\OpenAIException(
                "OpenAI streaming failed: {$e->getMessage()}",
                \App\Exceptions\OpenAIException::STREAMING_FAILED,
                $e
            );
        }
    }

    /**
     * Get available models
     *
     * @return array List of available models
     */
    public function getAvailableModels(): array
    {
        return [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo',
        ];
    }

    /**
     * Create HTTP client with proper headers
     *
     *
     * @throws \RuntimeException
     */
    protected function client(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        return Http::withHeaders($headers)
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * Get OpenAI log channel
     */
    protected function logChannel(): LoggerInterface
    {
        return Log::channel('openai');
    }

    /**
     * Make HTTP request to OpenAI API
     *
     * @param  string  $method  HTTP method
     * @param  string  $url  API endpoint
     * @param  array  $payload  Request payload
     * @return array Response data
     *
     * @throws Throwable
     */
    protected function request(string $method, string $url, array $payload = []): array
    {
        $reqId = (string) Str::uuid();
        $start = microtime(true);

        $this->logChannel()->info('openai.request', [
            'event' => 'openai.request',
            'request_id' => $reqId,
            'method' => strtoupper($method),
            'url' => ltrim($url, '/'),
            'payload' => $payload,
        ]);

        try {
            // Wrap API call with circuit breaker
            $resp = $this->circuitBreaker->call(function () use ($method, $url, $payload) {
                $pendingRequest = $this->client();

                if (! count($payload)) {
                    return $pendingRequest->send($method, ltrim($url, '/'));
                } else {
                    return $pendingRequest->send($method, ltrim($url, '/'), [
                        'json' => $payload,
                    ]);
                }
            });

            $duration = (int) round((microtime(true) - $start) * 1000);

            $status = $resp->status();
            $body = (string) $resp->body();
            $json = null;

            try {
                $json = $resp->json();
            } catch (Throwable $e) {
                $json = null;
            }

            $this->logChannel()->info('openai.response', [
                'event' => 'openai.response',
                'request_id' => $reqId,
                'status' => $status,
                'duration_ms' => $duration,
                'response' => $json ?? [
                    'text' => Str::limit($body, 2000),
                ],
            ]);

            $resp->throw();

            return $json ?? [];
        } catch (Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $code = method_exists($e, 'getCode') ? $e->getCode() : 0;

            $this->logChannel()->error('openai.error', [
                'event' => 'openai.error',
                'request_id' => $reqId,
                'duration_ms' => $duration,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $code,
                    'class' => get_class($e),
                ],
            ]);

            throw $e;
        }
    }
}
