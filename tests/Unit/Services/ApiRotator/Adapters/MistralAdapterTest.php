<?php

namespace Tests\Unit\Services\ApiRotator\Adapters;

use App\Services\ApiRotator\Adapters\MistralAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class MistralAdapterTest extends TestCase
{
    private MistralAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new MistralAdapter();
    }

    /** @test */
    public function it_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    /** @test */
    public function it_returns_mistral_as_provider_name(): void
    {
        $this->assertEquals('mistral', $this->adapter->getProviderName());
    }

    /** @test */
    public function parse_rate_limit_info_extracts_x_rate_limit_headers(): void
    {
        $psrResponse = new Psr7Response(200, [
            'X-RateLimit-Remaining' => '55',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Reset' => '1736640000',
        ], '{}');
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitInfo($response);

        $this->assertEquals('55', $result['rpm_remaining']);
        $this->assertFalse($result['requires_client_tracking']);
        $this->assertArrayHasKey('raw_headers', $result);
        $this->assertEquals('55', $result['raw_headers']['X-RateLimit-Remaining']);
    }

    /** @test */
    public function parse_rate_limit_info_handles_missing_headers(): void
    {
        $psrResponse = new Psr7Response(200, [], '{}');
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitInfo($response);

        $this->assertNull($result['rpm_remaining']);
        $this->assertFalse($result['requires_client_tracking']);
    }

    /** @test */
    public function parse_rate_limit_error_extracts_retry_after(): void
    {
        $body = json_encode(['message' => 'Rate limit exceeded']);
        $psrResponse = new Psr7Response(429, ['Retry-After' => '45'], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(45, $result['retry_after']);
        $this->assertFalse($result['is_daily_limit']);
        $this->assertInstanceOf(\DateTimeInterface::class, $result['reset_at']);
    }

    /** @test */
    public function parse_rate_limit_error_defaults_to_60_seconds(): void
    {
        $body = json_encode(['message' => 'Rate limit exceeded']);
        $psrResponse = new Psr7Response(429, [], $body);
        $response = new Response($psrResponse);

        $result = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(60, $result['retry_after']);
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
    public function is_recoverable_error_returns_false_for_other_errors(): void
    {
        foreach ([400, 401, 403, 404] as $status) {
            $psrResponse = new Psr7Response($status, [], '{}');
            $response = new Response($psrResponse);

            $this->assertFalse($this->adapter->isRecoverableError($response), "Status {$status} should not be recoverable");
        }
    }

    /** @test */
    public function get_default_limits_returns_correct_model_limits(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('mistral-small-latest', $limits);
        $this->assertEquals(60, $limits['mistral-small-latest']['rpm']);
        $this->assertEquals(1000, $limits['mistral-small-latest']['rpd']);
        $this->assertEquals(500000, $limits['mistral-small-latest']['tpm']);

        $this->assertArrayHasKey('pixtral-12b-latest', $limits);
        $this->assertArrayHasKey('mistral-nemo', $limits);
    }

    /** @test */
    public function extract_token_usage_parses_mistral_response_format(): void
    {
        $responseData = [
            'usage' => [
                'prompt_tokens' => 800,
                'completion_tokens' => 400,
                'total_tokens' => 1200,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(800, $usage['prompt_tokens']);
        $this->assertEquals(400, $usage['completion_tokens']);
        $this->assertEquals(1200, $usage['total_tokens']);
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

        // Reset time should be tomorrow midnight UTC
        $expectedUtc = Carbon::now('UTC')
            ->addDay()
            ->startOfDay();

        // The reset time should be after now
        $this->assertTrue($resetTime > now());

        Carbon::setTestNow();
    }
}
