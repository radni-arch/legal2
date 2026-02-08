<?php

namespace Tests\Integration\ApiRotator;

use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

/**
 * Integration tests for OpenRouter API response parsing.
 *
 * These tests verify that the OpenRouterAdapter correctly parses realistic
 * API responses including the output array format, rate limit errors with
 * metadata.headers, and distinguishing between daily and minute limits.
 */
class OpenRouterResponseParsingTest extends TestCase
{
    private OpenRouterAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new OpenRouterAdapter();
    }

    // ====================================================================
    // Successful Response Parsing Tests
    // ====================================================================

    /** @test */
    public function parses_successful_response_with_output_array_format(): void
    {
        $responseData = [
            'id' => 'gen-abc123',
            'model' => 'google/gemini-2.0-flash-exp:free',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => '{"case_summary": "Legal analysis complete", "verdict": "favorable"}',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 4200,
                'completion_tokens' => 1100,
                'total_tokens' => 5300,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(4200, $usage['prompt_tokens']);
        $this->assertEquals(1100, $usage['completion_tokens']);
        $this->assertEquals(5300, $usage['total_tokens']);
    }

    /** @test */
    public function parses_response_with_reasoning_and_output(): void
    {
        $responseData = [
            'id' => 'gen-xyz789',
            'model' => 'google/gemini-2.0-flash-thinking-exp:free',
            'output' => [
                [
                    'type' => 'reasoning',
                    'content' => [
                        [
                            'type' => 'reasoning_text',
                            'text' => 'Analyzing the legal document structure...',
                        ],
                    ],
                ],
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => '{"analysis": "Document contains valid legal clauses"}',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 2000,
                'completion_tokens' => 800,
                'total_tokens' => 2800,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(2000, $usage['prompt_tokens']);
        $this->assertEquals(800, $usage['completion_tokens']);
        $this->assertEquals(2800, $usage['total_tokens']);
    }

    /** @test */
    public function parses_response_with_multiple_output_types(): void
    {
        $responseData = [
            'id' => 'gen-multi123',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'First output segment',
                        ],
                        [
                            'type' => 'output_text',
                            'text' => 'Second output segment',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 500,
                'completion_tokens' => 300,
                'total_tokens' => 800,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(800, $usage['total_tokens']);
    }

    /** @test */
    public function rate_limit_info_requires_client_tracking(): void
    {
        $response = new Response(
            new GuzzleResponse(200, [], json_encode([
                'output' => [],
                'usage' => ['total_tokens' => 100],
            ]))
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertTrue($rateLimitInfo['requires_client_tracking']);
        $this->assertNull($rateLimitInfo['rpm_remaining']);
        $this->assertNull($rateLimitInfo['rpd_remaining']);
    }

    // ====================================================================
    // Rate Limit Error Parsing with metadata.headers
    // ====================================================================

    /** @test */
    public function parses_rate_limit_error_with_metadata_headers(): void
    {
        Carbon::setTestNow('2026-01-12 10:00:00');

        $resetMs = (Carbon::now()->addMinutes(5)->timestamp * 1000);

        $errorResponse = [
            'error' => [
                'message' => 'Rate limit exceeded',
                'code' => 429,
                'metadata' => [
                    'headers' => [
                        'X-RateLimit-Limit' => '20',
                        'X-RateLimit-Remaining' => '0',
                        'X-RateLimit-Reset' => (string) $resetMs,
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertInstanceOf(\DateTimeInterface::class, $parsed['reset_at']);
        $this->assertFalse($parsed['is_daily_limit']);
        $this->assertEquals('0', $parsed['rpm_remaining']);
        $this->assertEquals('20', $parsed['rpm_limit']);
        $this->assertArrayHasKey('raw_metadata', $parsed);

        Carbon::setTestNow();
    }

    /** @test */
    public function parses_daily_limit_error_from_message(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Rate limit exceeded: limit_rpd. Daily requests per day quota exhausted.',
                'code' => 429,
                'metadata' => [
                    'headers' => [],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($parsed['is_daily_limit']);
        $this->assertStringContainsString('limit_rpd', $parsed['error_message']);
    }

    /** @test */
    public function parses_minute_limit_error_as_non_daily(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Rate limit exceeded: limit_rpm. Please slow down your requests.',
                'code' => 429,
                'metadata' => [
                    'headers' => [
                        'X-RateLimit-Remaining' => '0',
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertFalse($parsed['is_daily_limit']);
    }

    /** @test */
    public function parses_rate_limit_error_without_metadata(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Too many requests',
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        // Should default to 60 seconds when no reset info
        $this->assertEquals(60, $parsed['retry_after']);
        $this->assertInstanceOf(\DateTimeInterface::class, $parsed['reset_at']);
    }

    /** @test */
    public function calculates_retry_after_from_reset_timestamp(): void
    {
        Carbon::setTestNow('2026-01-12 10:00:00');

        // Reset in 2 minutes
        $resetMs = (Carbon::now()->addMinutes(2)->timestamp * 1000);

        $errorResponse = [
            'error' => [
                'message' => 'Rate limited',
                'metadata' => [
                    'headers' => [
                        'X-RateLimit-Reset' => (string) $resetMs,
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        // Retry after should be approximately 120 seconds
        $this->assertGreaterThan(100, $parsed['retry_after']);
        $this->assertLessThan(140, $parsed['retry_after']);

        Carbon::setTestNow();
    }

    // ====================================================================
    // Daily vs Minute Limit Identification Tests
    // ====================================================================

    /** @test */
    public function identifies_daily_limit_variants_in_error_message(): void
    {
        $dailyLimitMessages = [
            'Rate limit exceeded: limit_rpd',
            'limit_rpd reached for today',
            'Daily limit_rpd exhausted',
        ];

        foreach ($dailyLimitMessages as $message) {
            $errorResponse = [
                'error' => [
                    'message' => $message,
                    'metadata' => ['headers' => []],
                ],
            ];

            $response = new Response(
                new GuzzleResponse(429, [], json_encode($errorResponse))
            );

            $parsed = $this->adapter->parseRateLimitError($response);

            $this->assertTrue($parsed['is_daily_limit'], "Message '{$message}' should be identified as daily limit");
        }
    }

    /** @test */
    public function identifies_minute_limit_as_non_daily(): void
    {
        $minuteLimitMessages = [
            'Rate limit exceeded',
            'Too many requests per minute',
            'limit_rpm exceeded',
            'Slow down',
        ];

        foreach ($minuteLimitMessages as $message) {
            $errorResponse = [
                'error' => [
                    'message' => $message,
                    'metadata' => ['headers' => []],
                ],
            ];

            $response = new Response(
                new GuzzleResponse(429, [], json_encode($errorResponse))
            );

            $parsed = $this->adapter->parseRateLimitError($response);

            $this->assertFalse($parsed['is_daily_limit'], "Message '{$message}' should NOT be identified as daily limit");
        }
    }

    // ====================================================================
    // Recoverable vs Non-Recoverable Error Tests
    // ====================================================================

    /** @test */
    public function identifies_429_minute_limit_as_recoverable(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Rate limit exceeded',
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_429_daily_limit_as_non_recoverable(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Rate limit exceeded: limit_rpd',
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_server_errors_as_recoverable(): void
    {
        foreach ([500, 502, 503] as $code) {
            $response = new Response(
                new GuzzleResponse($code, [], json_encode(['error' => ['message' => 'Server error']]))
            );

            $this->assertTrue(
                $this->adapter->isRecoverableError($response),
                "HTTP {$code} should be recoverable"
            );
        }
    }

    /** @test */
    public function identifies_client_errors_as_non_recoverable(): void
    {
        foreach ([400, 401, 403, 404] as $code) {
            $response = new Response(
                new GuzzleResponse($code, [], json_encode(['error' => ['message' => 'Client error']]))
            );

            $this->assertFalse(
                $this->adapter->isRecoverableError($response),
                "HTTP {$code} should NOT be recoverable"
            );
        }
    }

    // ====================================================================
    // Edge Cases and Special Scenarios
    // ====================================================================

    /** @test */
    public function handles_empty_output_array(): void
    {
        $responseData = [
            'output' => [],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 0,
                'total_tokens' => 100,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(100, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(100, $usage['total_tokens']);
    }

    /** @test */
    public function handles_missing_usage_data(): void
    {
        $responseData = [
            'output' => [
                ['type' => 'message', 'role' => 'assistant', 'content' => []],
            ],
            // No usage field
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    /** @test */
    public function computes_safe_max_output_tokens_for_small_pdf(): void
    {
        // Simulate a small PDF (about 4KB base64)
        $smallPdfBase64 = str_repeat('a', 4000);

        $maxTokens = $this->adapter->computeSafeMaxOutputTokens($smallPdfBase64);

        $this->assertGreaterThanOrEqual(256, $maxTokens);
        $this->assertLessThanOrEqual(4096, $maxTokens);
    }

    /** @test */
    public function computes_safe_max_output_tokens_for_medium_pdf(): void
    {
        // Simulate a medium PDF (about 100KB base64)
        $mediumPdfBase64 = str_repeat('a', 100000);

        $maxTokens = $this->adapter->computeSafeMaxOutputTokens($mediumPdfBase64);

        $this->assertGreaterThanOrEqual(256, $maxTokens);
        $this->assertLessThanOrEqual(4096, $maxTokens);
    }

    /** @test */
    public function computes_minimum_tokens_for_oversized_pdf(): void
    {
        // Simulate an oversized PDF that would exceed context
        $largePdfBase64 = str_repeat('a', 700000);

        $maxTokens = $this->adapter->computeSafeMaxOutputTokens($largePdfBase64);

        $this->assertEquals(256, $maxTokens);
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
    public function returns_default_limits_for_free_and_paid_tiers(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('free', $limits);
        $this->assertEquals(20, $limits['free']['rpm']);
        $this->assertEquals(50, $limits['free']['rpd']);

        $this->assertArrayHasKey('paid', $limits);
        $this->assertEquals(20, $limits['paid']['rpm']);
        $this->assertEquals(1000, $limits['paid']['rpd']);
    }

    /** @test */
    public function preserves_raw_metadata_in_rate_limit_error(): void
    {
        $errorResponse = [
            'error' => [
                'message' => 'Rate limit',
                'metadata' => [
                    'headers' => [
                        'X-Custom-Header' => 'custom-value',
                        'X-RateLimit-Limit' => '20',
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertArrayHasKey('raw_metadata', $parsed);
        $this->assertEquals('custom-value', $parsed['raw_metadata']['X-Custom-Header']);
    }
}
