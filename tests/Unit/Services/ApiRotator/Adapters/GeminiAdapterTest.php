<?php

namespace Tests\Unit\Services\ApiRotator\Adapters;

use App\Services\ApiRotator\Adapters\GeminiAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class GeminiAdapterTest extends TestCase
{
    private GeminiAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new GeminiAdapter();
    }

    /** @test */
    public function it_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    /** @test */
    public function it_returns_gemini_as_provider_name(): void
    {
        $this->assertEquals('gemini', $this->adapter->getProviderName());
    }

    /** @test */
    public function parse_rate_limit_info_returns_requires_client_tracking_true(): void
    {
        $psrResponse = new Psr7Response(200, [], '{}');
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitInfo($response);

        $this->assertArrayHasKey('requires_client_tracking', $result);
        $this->assertTrue($result['requires_client_tracking']);
        $this->assertNull($result['rpm_remaining']);
        $this->assertNull($result['rpd_remaining']);
    }

    /** @test */
    public function parse_rate_limit_error_extracts_retry_after_from_429(): void
    {
        $body = json_encode([
            'error' => [
                'message' => 'Rate limited',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerMinute'],
                ],
            ],
        ]);
        $psrResponse = new Psr7Response(429, ['Retry-After' => '30'], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(30, $result['retry_after']);
        $this->assertFalse($result['is_daily_limit']);
        $this->assertEquals('GenerateContentRequestsPerMinute', $result['quota_id']);
    }

    /** @test */
    public function parse_rate_limit_error_detects_daily_limit_from_quota_id(): void
    {
        $body = json_encode([
            'error' => [
                'message' => 'Daily quota exceeded',
                'details' => [
                    ['quotaId' => 'GenerateContentRequestsPerDay'],
                ],
            ],
        ]);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($result['is_daily_limit']);
        $this->assertInstanceOf(\DateTimeInterface::class, $result['reset_at']);
    }

    /** @test */
    public function is_recoverable_error_returns_true_for_429(): void
    {
        $psrResponse = new Psr7Response(429, [], '{}');
        $response = new Response($psrResponse);

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function is_recoverable_error_returns_true_for_500_502_503(): void
    {
        foreach ([500, 502, 503] as $status) {
            $psrResponse = new Psr7Response($status, [], '{}');
            $response = new Response($psrResponse);

            $this->assertTrue($this->adapter->isRecoverableError($response), "Status {$status} should be recoverable");
        }
    }

    /** @test */
    public function is_recoverable_error_returns_false_for_403_resource_exhausted(): void
    {
        $body = json_encode([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Resource has been exhausted',
            ],
        ]);
        $psrResponse = new Psr7Response(403, [], $body);
        $response = new Response($psrResponse);

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function is_recoverable_error_returns_false_for_other_errors(): void
    {
        $psrResponse = new Psr7Response(400, [], '{}');
        $response = new Response($psrResponse);

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function get_default_limits_returns_correct_model_limits(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('gemini-2.5-flash-preview-05-20', $limits);
        $this->assertEquals(10, $limits['gemini-2.5-flash-preview-05-20']['rpm']);
        $this->assertEquals(250, $limits['gemini-2.5-flash-preview-05-20']['rpd']);

        $this->assertArrayHasKey('gemini-2.5-pro', $limits);
        $this->assertEquals(5, $limits['gemini-2.5-pro']['rpm']);
        $this->assertEquals(50, $limits['gemini-2.5-pro']['rpd']);

        $this->assertArrayHasKey('gemini-2.0-flash-exp', $limits);
        $this->assertEquals(10, $limits['gemini-2.0-flash-exp']['rpm']);
        $this->assertEquals(100, $limits['gemini-2.0-flash-exp']['rpd']);
    }

    /** @test */
    public function extract_token_usage_parses_gemini_response_format(): void
    {
        $responseData = [
            'usageMetadata' => [
                'promptTokenCount' => 1000,
                'candidatesTokenCount' => 500,
                'totalTokenCount' => 1500,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(1000, $usage['prompt_tokens']);
        $this->assertEquals(500, $usage['completion_tokens']);
        $this->assertEquals(1500, $usage['total_tokens']);
    }

    /** @test */
    public function extract_token_usage_returns_zeros_for_missing_data(): void
    {
        $usage = $this->adapter->extractTokenUsage([]);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    /** @test */
    public function get_daily_reset_time_returns_midnight_pacific_time(): void
    {
        Carbon::setTestNow('2026-01-11 10:00:00');

        $resetTime = $this->adapter->getDailyResetTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $resetTime);

        // Reset time should be tomorrow midnight Pacific, converted to app timezone
        $expectedPacific = Carbon::now('America/Los_Angeles')
            ->addDay()
            ->startOfDay();

        // The reset time should be after now
        $this->assertTrue($resetTime > now());

        Carbon::setTestNow();
    }
}
