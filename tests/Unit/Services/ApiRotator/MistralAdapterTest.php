<?php

namespace Tests\Unit\Services\ApiRotator;

use App\Models\ApiKey;
use App\Services\ApiRotator\Adapters\MistralAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
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

    public function test_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    public function test_get_provider_name_returns_mistral(): void
    {
        $this->assertEquals('mistral', $this->adapter->getProviderName());
    }

    public function test_get_default_limits_returns_expected_models(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('mistral-small-latest', $limits);
        $this->assertArrayHasKey('pixtral-12b-latest', $limits);
        $this->assertArrayHasKey('mistral-nemo', $limits);

        $small = $limits['mistral-small-latest'];
        $this->assertEquals(60, $small['rpm']);
        $this->assertEquals(1000, $small['rpd']);
        $this->assertEquals(500000, $small['tpm']);
    }

    public function test_extract_token_usage_parses_openai_format(): void
    {
        $responseData = [
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

    public function test_extract_token_usage_handles_missing_data(): void
    {
        $usage = $this->adapter->extractTokenUsage([]);

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
    }

    public function test_get_daily_reset_time_returns_utc_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-11 10:00:00', 'UTC'));

        $resetTime = $this->adapter->getDailyResetTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $resetTime);
        $this->assertTrue($resetTime->isFuture());
    }

    public function test_parse_rate_limit_info_extracts_headers(): void
    {
        $response = $this->createMockResponse(200, [], [
            'X-RateLimit-Remaining' => '55',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Reset' => (string) time() + 60,
        ]);

        $info = $this->adapter->parseRateLimitInfo($response);

        $this->assertEquals('55', $info['rpm_remaining']);
        $this->assertFalse($info['requires_client_tracking']);
    }

    public function test_parse_rate_limit_error_extracts_retry_after(): void
    {
        $response = $this->createMockResponse(429, [
            'message' => 'Rate limited',
        ], ['Retry-After' => '30']);

        $info = $this->adapter->parseRateLimitError($response);

        $this->assertEquals(30, $info['retry_after']);
        $this->assertFalse($info['is_daily_limit']);
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
