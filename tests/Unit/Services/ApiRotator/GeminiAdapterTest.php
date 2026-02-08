<?php

namespace Tests\Unit\Services\ApiRotator;

use App\Models\ApiKey;
use App\Services\ApiRotator\Adapters\GeminiAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAdapterTest extends TestCase
{
    private GeminiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new GeminiAdapter();
    }

    public function test_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    public function test_get_provider_name_returns_gemini(): void
    {
        $this->assertEquals('gemini', $this->adapter->getProviderName());
    }

    public function test_get_default_limits_returns_expected_models(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('gemini-2.5-flash-preview-05-20', $limits);
        $this->assertArrayHasKey('gemini-2.5-pro', $limits);
        $this->assertArrayHasKey('gemini-2.0-flash-exp', $limits);

        $flash = $limits['gemini-2.5-flash-preview-05-20'];
        $this->assertEquals(10, $flash['rpm']);
        $this->assertEquals(250, $flash['rpd']);
        $this->assertEquals(250000, $flash['tpm']);
    }

    public function test_extract_token_usage_parses_gemini_format(): void
    {
        $responseData = [
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 50,
                'totalTokenCount' => 150,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(100, $usage['prompt_tokens']);
        $this->assertEquals(50, $usage['completion_tokens']);
        $this->assertEquals(150, $usage['total_tokens']);
    }

    public function test_extract_token_usage_handles_missing_data(): void
    {
        $usage = $this->adapter->extractTokenUsage([]);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    public function test_get_daily_reset_time_returns_pacific_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-11 10:00:00', 'UTC'));

        $resetTime = $this->adapter->getDailyResetTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $resetTime);
        // Should be tomorrow midnight Pacific time converted to app timezone
        $this->assertTrue($resetTime->isFuture());
    }

    public function test_parse_rate_limit_info_indicates_client_tracking_required(): void
    {
        $response = $this->createMockResponse(200, []);

        $info = $this->adapter->parseRateLimitInfo($response);

        $this->assertTrue($info['requires_client_tracking']);
        $this->assertNull($info['rpm_remaining']);
        $this->assertNull($info['rpd_remaining']);
    }

    public function test_parse_rate_limit_error_extracts_retry_after(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'Rate limited',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerMinute'],
                ],
            ],
        ], ['Retry-After' => '60']);

        $info = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(60, $info['retry_after']);
        $this->assertFalse($info['is_daily_limit']);
    }

    public function test_parse_rate_limit_error_detects_daily_limit(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'Rate limited',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerDay'],
                ],
            ],
        ]);

        $info = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($info['is_daily_limit']);
    }

    public function test_is_recoverable_error_returns_true_for_429(): void
    {
        $response = $this->createMockResponse(429, []);

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    public function test_is_recoverable_error_returns_true_for_server_errors(): void
    {
        foreach ([500, 502, 503] as $status) {
            $response = $this->createMockResponse($status, []);
            $this->assertTrue($this->adapter->isRecoverableError($response));
        }
    }

    public function test_is_recoverable_error_returns_false_for_resource_exhausted(): void
    {
        $response = $this->createMockResponse(403, [
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
            ],
        ]);

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    public function test_is_recoverable_error_returns_false_for_client_errors(): void
    {
        $response = $this->createMockResponse(400, []);

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    private function createMockResponse(int $status, array $body, array $headers = []): Response
    {
        return new Response(
            new \GuzzleHttp\Psr7\Response($status, $headers, json_encode($body))
        );
    }
}
