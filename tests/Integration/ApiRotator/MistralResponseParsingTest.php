<?php

namespace Tests\Integration\ApiRotator;

use App\Services\ApiRotator\Adapters\MistralAdapter;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

/**
 * Integration tests for Mistral API response parsing.
 *
 * These tests verify that the MistralAdapter correctly parses realistic
 * API responses including success responses with X-RateLimit headers,
 * rate limit errors, and various error conditions.
 */
class MistralResponseParsingTest extends TestCase
{
    private MistralAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new MistralAdapter();
    }

    // ====================================================================
    // Successful Response Parsing Tests
    // ====================================================================

    /** @test */
    public function parses_successful_response_with_choices_and_usage(): void
    {
        $responseData = [
            'id' => 'cmpl-abc123',
            'object' => 'chat.completion',
            'created' => 1736640000,
            'model' => 'mistral-small-latest',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"document_analysis": "comprehensive legal review", "key_findings": ["finding1", "finding2"]}',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 3500,
                'completion_tokens' => 850,
                'total_tokens' => 4350,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(3500, $usage['prompt_tokens']);
        $this->assertEquals(850, $usage['completion_tokens']);
        $this->assertEquals(4350, $usage['total_tokens']);
    }

    /** @test */
    public function parses_successful_response_with_x_ratelimit_headers(): void
    {
        $responseData = [
            'choices' => [
                ['message' => ['content' => 'response'], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
        ];

        $response = new Response(
            new GuzzleResponse(200, [
                'X-RateLimit-Limit' => '60',
                'X-RateLimit-Remaining' => '45',
                'X-RateLimit-Reset' => '1736640060', // Unix timestamp
            ], json_encode($responseData))
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertEquals('45', $rateLimitInfo['rpm_remaining']);
        $this->assertFalse($rateLimitInfo['requires_client_tracking']);
        $this->assertArrayHasKey('raw_headers', $rateLimitInfo);
        $this->assertEquals('60', $rateLimitInfo['raw_headers']['X-RateLimit-Limit']);
        $this->assertEquals('45', $rateLimitInfo['raw_headers']['X-RateLimit-Remaining']);
    }

    /** @test */
    public function parses_reset_header_as_unix_timestamp(): void
    {
        $resetTimestamp = Carbon::now()->addMinutes(5)->timestamp;

        $response = new Response(
            new GuzzleResponse(200, [
                'X-RateLimit-Remaining' => '10',
                'X-RateLimit-Reset' => (string) $resetTimestamp,
            ], '{}')
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertNotNull($rateLimitInfo['reset_at']);
        $this->assertInstanceOf(\DateTimeInterface::class, $rateLimitInfo['reset_at']);
    }

    /** @test */
    public function parses_response_with_tool_calls(): void
    {
        $responseData = [
            'id' => 'cmpl-xyz789',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'extract_entities',
                                    'arguments' => '{"entities": ["party1", "party2"]}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 200,
                'completion_tokens' => 100,
                'total_tokens' => 300,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(200, $usage['prompt_tokens']);
        $this->assertEquals(100, $usage['completion_tokens']);
        $this->assertEquals(300, $usage['total_tokens']);
    }

    // ====================================================================
    // Rate Limit Header Parsing Tests
    // ====================================================================

    /** @test */
    public function extracts_all_rate_limit_headers(): void
    {
        $response = new Response(
            new GuzzleResponse(200, [
                'X-RateLimit-Limit' => '60',
                'X-RateLimit-Remaining' => '30',
                'X-RateLimit-Reset' => '1736640000',
                'Retry-After' => '30',
            ], '{}')
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertEquals('30', $rateLimitInfo['rpm_remaining']);
        $this->assertArrayHasKey('raw_headers', $rateLimitInfo);
        $this->assertCount(4, $rateLimitInfo['raw_headers']);
    }

    /** @test */
    public function handles_missing_rate_limit_headers_gracefully(): void
    {
        $response = new Response(
            new GuzzleResponse(200, ['Content-Type' => 'application/json'], '{}')
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertNull($rateLimitInfo['rpm_remaining']);
        $this->assertNull($rateLimitInfo['rpd_remaining']);
        $this->assertNull($rateLimitInfo['retry_after']);
        $this->assertFalse($rateLimitInfo['requires_client_tracking']);
    }

    // ====================================================================
    // Rate Limit Error Parsing Tests
    // ====================================================================

    /** @test */
    public function parses_rate_limit_error_with_retry_after_header(): void
    {
        $errorResponse = [
            'message' => 'Rate limit exceeded. Please retry after 45 seconds.',
            'request_id' => 'req_abc123',
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '45'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(45, $parsed['retry_after']);
        $this->assertFalse($parsed['is_daily_limit']); // Mistral primarily uses per-minute limits
        $this->assertInstanceOf(\DateTimeInterface::class, $parsed['reset_at']);
    }

    /** @test */
    public function parses_rate_limit_error_without_retry_after_defaults_to_60(): void
    {
        $errorResponse = [
            'message' => 'Rate limit exceeded',
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(60, $parsed['retry_after']);
    }

    /** @test */
    public function parses_rate_limit_error_with_detailed_message(): void
    {
        $errorResponse = [
            'message' => 'Requests rate limit exceeded. You have sent too many requests in a short time. Please slow down.',
            'object' => 'error',
            'type' => 'rate_limit_error',
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '30'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertStringContainsString('too many requests', strtolower($parsed['error_message']));
        $this->assertEquals(30, $parsed['retry_after']);
    }

    // ====================================================================
    // Recoverable vs Non-Recoverable Error Tests
    // ====================================================================

    /** @test */
    public function identifies_429_as_recoverable_error(): void
    {
        $response = new Response(
            new GuzzleResponse(429, [], json_encode(['message' => 'Rate limited']))
        );

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_server_errors_as_recoverable(): void
    {
        foreach ([500, 502, 503] as $code) {
            $response = new Response(
                new GuzzleResponse($code, [], json_encode(['message' => 'Server error']))
            );

            $this->assertTrue(
                $this->adapter->isRecoverableError($response),
                "HTTP {$code} should be recoverable"
            );
        }
    }

    /** @test */
    public function identifies_401_unauthorized_as_non_recoverable(): void
    {
        $response = new Response(
            new GuzzleResponse(401, [], json_encode(['message' => 'Unauthorized']))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_400_bad_request_as_non_recoverable(): void
    {
        $errorResponse = [
            'message' => 'Invalid request body',
            'object' => 'error',
            'type' => 'invalid_request_error',
        ];

        $response = new Response(
            new GuzzleResponse(400, [], json_encode($errorResponse))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_403_forbidden_as_non_recoverable(): void
    {
        $response = new Response(
            new GuzzleResponse(403, [], json_encode(['message' => 'Forbidden']))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_404_not_found_as_non_recoverable(): void
    {
        $response = new Response(
            new GuzzleResponse(404, [], json_encode(['message' => 'Model not found']))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    // ====================================================================
    // Edge Cases and Special Scenarios
    // ====================================================================

    /** @test */
    public function handles_empty_usage_data_gracefully(): void
    {
        $responseData = [
            'choices' => [
                ['message' => ['content' => 'response'], 'finish_reason' => 'stop'],
            ],
            // No usage field
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    /** @test */
    public function handles_partial_usage_data(): void
    {
        $responseData = [
            'usage' => [
                'prompt_tokens' => 500,
                // Missing completion_tokens and total_tokens
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(500, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    /** @test */
    public function daily_reset_time_is_in_the_future(): void
    {
        Carbon::setTestNow('2026-01-12 14:30:00');

        $resetTime = $this->adapter->getDailyResetTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $resetTime);
        $this->assertTrue($resetTime > now(), 'Reset time should be in the future');

        Carbon::setTestNow();
    }

    /** @test */
    public function parses_iso8601_reset_header(): void
    {
        $isoDate = Carbon::now()->addMinutes(10)->toIso8601String();

        $response = new Response(
            new GuzzleResponse(200, [
                'X-RateLimit-Remaining' => '20',
                'X-RateLimit-Reset' => $isoDate,
            ], '{}')
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertNotNull($rateLimitInfo['reset_at']);
        $this->assertInstanceOf(\DateTimeInterface::class, $rateLimitInfo['reset_at']);
    }

    /** @test */
    public function returns_default_limits_for_known_models(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('mistral-small-latest', $limits);
        $this->assertArrayHasKey('pixtral-12b-latest', $limits);
        $this->assertArrayHasKey('mistral-nemo', $limits);

        // Verify structure of limits
        foreach ($limits as $model => $modelLimits) {
            $this->assertArrayHasKey('rpm', $modelLimits, "Model {$model} should have rpm limit");
            $this->assertArrayHasKey('rpd', $modelLimits, "Model {$model} should have rpd limit");
            $this->assertArrayHasKey('tpm', $modelLimits, "Model {$model} should have tpm limit");
        }
    }
}
