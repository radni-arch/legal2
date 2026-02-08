<?php

namespace Tests\Integration\ApiRotator;

use App\Services\ApiRotator\Adapters\GeminiAdapter;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

/**
 * Integration tests for Gemini API response parsing.
 *
 * These tests verify that the GeminiAdapter correctly parses realistic
 * API responses including success responses, rate limit errors, and
 * various error conditions.
 */
class GeminiResponseParsingTest extends TestCase
{
    private GeminiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new GeminiAdapter();
    }

    // ====================================================================
    // Successful Response Parsing Tests
    // ====================================================================

    /** @test */
    public function parses_successful_response_with_candidates_and_usage_metadata(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '{"extracted_data": "legal document content", "confidence": 0.95}'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                    'index' => 0,
                    'safetyRatings' => [
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'probability' => 'NEGLIGIBLE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'probability' => 'NEGLIGIBLE'],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 2500,
                'candidatesTokenCount' => 1200,
                'totalTokenCount' => 3700,
            ],
            'modelVersion' => 'gemini-2.5-flash-preview-05-20',
        ];

        $response = new Response(new GuzzleResponse(200, [], json_encode($responseData)));

        // Test through extractTokenUsage
        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(2500, $usage['prompt_tokens']);
        $this->assertEquals(1200, $usage['completion_tokens']);
        $this->assertEquals(3700, $usage['total_tokens']);

        // Test rate limit info indicates client tracking required
        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);
        $this->assertTrue($rateLimitInfo['requires_client_tracking']);
        $this->assertNull($rateLimitInfo['rpm_remaining']);
    }

    /** @test */
    public function parses_successful_response_with_json_object_content(): void
    {
        $jsonContent = [
            'document_type' => 'court_decision',
            'case_number' => '2024-CV-12345',
            'parties' => ['Plaintiff', 'Defendant'],
            'ruling_summary' => 'Motion granted',
        ];

        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => json_encode($jsonContent)]],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 5000,
                'candidatesTokenCount' => 300,
                'totalTokenCount' => 5300,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(5000, $usage['prompt_tokens']);
        $this->assertEquals(300, $usage['completion_tokens']);
        $this->assertEquals(5300, $usage['total_tokens']);
    }

    /** @test */
    public function parses_response_with_multiple_candidates(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => ['parts' => [['text' => 'First candidate response']]],
                    'finishReason' => 'STOP',
                    'index' => 0,
                ],
                [
                    'content' => ['parts' => [['text' => 'Second candidate response']]],
                    'finishReason' => 'STOP',
                    'index' => 1,
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
                'totalTokenCount' => 150,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(150, $usage['total_tokens']);
    }

    // ====================================================================
    // Rate Limit Error Parsing Tests
    // ====================================================================

    /** @test */
    public function parses_rate_limit_error_with_retry_after_header(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 429,
                'message' => 'Resource has been exhausted (e.g. check quota).',
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.rpc.QuotaFailure',
                        'violations' => [
                            [
                                'subject' => 'project:12345',
                                'description' => 'GenerateContentRequestsPerMinute',
                            ],
                        ],
                        'quotaId' => 'GenerateContentRequestsPerMinutePerProjectPerModel',
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '45'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(45, $parsed['retry_after']);
        $this->assertFalse($parsed['is_daily_limit']);
        $this->assertEquals('GenerateContentRequestsPerMinutePerProjectPerModel', $parsed['quota_id']);
        $this->assertInstanceOf(\DateTimeInterface::class, $parsed['reset_at']);
    }

    /** @test */
    public function parses_daily_quota_exhaustion_error(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 429,
                'message' => 'Quota exceeded for quota metric \'Generate Content API requests per day\'',
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [
                    [
                        'quotaId' => 'GenerateContentRequestsPerDayPerProjectPerModel',
                    ],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '3600'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($parsed['is_daily_limit']);
        $this->assertStringContainsString('PerDay', $parsed['quota_id']);
        $this->assertInstanceOf(\DateTimeInterface::class, $parsed['reset_at']);
    }

    /** @test */
    public function parses_rate_limit_error_without_retry_after_header(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 429,
                'message' => 'Too many requests',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerMinute'],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, [], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        // Should default to 60 seconds when no Retry-After header
        $this->assertEquals(60, $parsed['retry_after']);
    }

    /** @test */
    public function parses_rate_limit_error_with_float_retry_after(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 429,
                'message' => 'Rate limited',
                'details' => [['quotaId' => 'TestQuota']],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(429, ['Retry-After' => '30.5'], json_encode($errorResponse))
        );

        $parsed = $this->adapter->parseRateLimitError($response);

        // Should ceil the float value
        $this->assertEquals(31, $parsed['retry_after']);
    }

    // ====================================================================
    // Recoverable vs Non-Recoverable Error Tests
    // ====================================================================

    /** @test */
    public function identifies_429_rate_limit_as_recoverable(): void
    {
        $response = new Response(
            new GuzzleResponse(429, [], json_encode(['error' => ['message' => 'Rate limited']]))
        );

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_server_errors_as_recoverable(): void
    {
        $serverErrorCodes = [500, 502, 503];

        foreach ($serverErrorCodes as $code) {
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
    public function identifies_403_resource_exhausted_as_non_recoverable(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 403,
                'message' => 'The caller does not have permission',
                'status' => 'RESOURCE_EXHAUSTED',
                'details' => [
                    ['reason' => 'RESOURCE_EXHAUSTED'],
                ],
            ],
        ];

        $response = new Response(
            new GuzzleResponse(403, [], json_encode($errorResponse))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_401_unauthorized_as_non_recoverable(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 401,
                'message' => 'API key not valid. Please pass a valid API key.',
                'status' => 'UNAUTHENTICATED',
            ],
        ];

        $response = new Response(
            new GuzzleResponse(401, [], json_encode($errorResponse))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function identifies_400_bad_request_as_non_recoverable(): void
    {
        $errorResponse = [
            'error' => [
                'code' => 400,
                'message' => 'Invalid argument',
                'status' => 'INVALID_ARGUMENT',
            ],
        ];

        $response = new Response(
            new GuzzleResponse(400, [], json_encode($errorResponse))
        );

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    // ====================================================================
    // Edge Cases and Special Scenarios
    // ====================================================================

    /** @test */
    public function handles_empty_usage_metadata_gracefully(): void
    {
        $responseData = [
            'candidates' => [
                ['content' => ['parts' => [['text' => 'response']]]],
            ],
            // No usageMetadata
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    /** @test */
    public function handles_partial_usage_metadata(): void
    {
        $responseData = [
            'usageMetadata' => [
                'promptTokenCount' => 100,
                // Missing candidatesTokenCount and totalTokenCount
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(100, $usage['prompt_tokens']);
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
    public function rate_limit_info_includes_raw_headers(): void
    {
        $response = new Response(
            new GuzzleResponse(200, [
                'X-Custom-Header' => 'some-value',
                'Content-Type' => 'application/json',
            ], '{}')
        );

        $rateLimitInfo = $this->adapter->parseRateLimitInfo($response);

        $this->assertArrayHasKey('raw_headers', $rateLimitInfo);
        $this->assertIsArray($rateLimitInfo['raw_headers']);
    }
}
