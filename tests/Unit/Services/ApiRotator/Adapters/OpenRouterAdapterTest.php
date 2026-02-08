<?php

namespace Tests\Unit\Services\ApiRotator\Adapters;

use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class OpenRouterAdapterTest extends TestCase
{
    private OpenRouterAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new OpenRouterAdapter();
    }

    /** @test */
    public function it_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    /** @test */
    public function it_returns_openrouter_as_provider_name(): void
    {
        $this->assertEquals('openrouter', $this->adapter->getProviderName());
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
    public function parse_rate_limit_error_extracts_x_rate_limit_reset_from_metadata(): void
    {
        Carbon::setTestNow('2026-01-11 10:00:00');

        $resetMs = (Carbon::now()->addMinutes(5)->timestamp * 1000);
        $body = json_encode([
            'error' => [
                'message' => 'Rate limit exceeded',
                'metadata' => [
                    'headers' => [
                        'X-RateLimit-Reset' => (string) $resetMs,
                        'X-RateLimit-Remaining' => '0',
                        'X-RateLimit-Limit' => '20',
                    ],
                ],
            ],
        ]);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertInstanceOf(\DateTimeInterface::class, $result['reset_at']);
        $this->assertArrayHasKey('retry_after', $result);
        $this->assertFalse($result['is_daily_limit']);

        Carbon::setTestNow();
    }

    /** @test */
    public function parse_rate_limit_error_detects_daily_limit_from_message(): void
    {
        $body = json_encode([
            'error' => [
                'message' => 'Rate limit exceeded: limit_rpd',
                'metadata' => ['headers' => []],
            ],
        ]);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($result['is_daily_limit']);
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
    public function is_recoverable_error_returns_true_for_429_without_daily_limit(): void
    {
        $body = json_encode([
            'error' => [
                'message' => 'Rate limit exceeded',
            ],
        ]);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function is_recoverable_error_returns_false_for_429_with_daily_limit(): void
    {
        $body = json_encode([
            'error' => [
                'message' => 'Rate limit exceeded: limit_rpd',
            ],
        ]);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $this->assertFalse($this->adapter->isRecoverableError($response));
    }

    /** @test */
    public function is_recoverable_error_returns_false_for_other_errors(): void
    {
        foreach ([400, 401, 403, 404] as $status) {
            $psrResponse = new Psr7Response($status, [], '{}');
            $response = new Response($psrResponse);

            $this->assertFalse($this->adapter->isRecoverableError($response), "Status {$status} should not be recoverable");
        }
    }

    /** @test */
    public function get_default_limits_returns_free_and_paid_tiers(): void
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
    public function extract_token_usage_parses_openrouter_response_format(): void
    {
        $responseData = [
            'usage' => [
                'prompt_tokens' => 1200,
                'completion_tokens' => 600,
                'total_tokens' => 1800,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(1200, $usage['prompt_tokens']);
        $this->assertEquals(600, $usage['completion_tokens']);
        $this->assertEquals(1800, $usage['total_tokens']);
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
    public function get_daily_reset_time_returns_midnight_utc(): void
    {
        Carbon::setTestNow('2026-01-11 10:00:00');

        $resetTime = $this->adapter->getDailyResetTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $resetTime);

        // The reset time should be after now
        $this->assertTrue($resetTime > now());

        Carbon::setTestNow();
    }

    /** @test */
    public function compute_safe_max_output_tokens_returns_reasonable_value(): void
    {
        // Test with a small PDF (about 1KB base64)
        $smallPdf = str_repeat('a', 1000);
        $result = $this->adapter->computeSafeMaxOutputTokens($smallPdf);

        $this->assertGreaterThanOrEqual(256, $result);
        $this->assertLessThanOrEqual(4096, $result);
    }

    /** @test */
    public function compute_safe_max_output_tokens_returns_minimum_for_large_pdf(): void
    {
        // Test with a very large PDF (would exceed context limit)
        $largePdf = str_repeat('a', 800000); // About 200K tokens
        $result = $this->adapter->computeSafeMaxOutputTokens($largePdf);

        $this->assertEquals(256, $result);
    }
}
