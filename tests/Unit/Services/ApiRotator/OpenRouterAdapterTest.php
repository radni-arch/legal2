<?php

namespace Tests\Unit\Services\ApiRotator;

use App\Models\ApiKey;
use App\Services\ApiRotator\Adapters\OpenRouterAdapter;
use App\Services\ApiRotator\Contracts\ProviderAdapterInterface;
use Carbon\Carbon;
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

    public function test_implements_provider_adapter_interface(): void
    {
        $this->assertInstanceOf(ProviderAdapterInterface::class, $this->adapter);
    }

    public function test_get_provider_name_returns_openrouter(): void
    {
        $this->assertEquals('openrouter', $this->adapter->getProviderName());
    }

    public function test_get_default_limits_returns_free_and_paid_tiers(): void
    {
        $limits = $this->adapter->getDefaultLimits();

        $this->assertArrayHasKey('free', $limits);
        $this->assertArrayHasKey('paid', $limits);

        $free = $limits['free'];
        $this->assertEquals(20, $free['rpm']);
        $this->assertEquals(50, $free['rpd']);
    }

    public function test_extract_token_usage_parses_openai_format(): void
    {
        $responseData = [
            'usage' => [
                'prompt_tokens' => 150,
                'completion_tokens' => 75,
                'total_tokens' => 225,
            ],
        ];

        $usage = $this->adapter->extractTokenUsage($responseData);

        $this->assertEquals(150, $usage['prompt_tokens']);
        $this->assertEquals(75, $usage['completion_tokens']);
        $this->assertEquals(225, $usage['total_tokens']);
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

    public function test_parse_rate_limit_info_requires_client_tracking(): void
    {
        $response = $this->createMockResponse(200, []);

        $info = $this->adapter->parseRateLimitInfo($response);

        $this->assertTrue($info['requires_client_tracking']);
        $this->assertNull($info['rpm_remaining']);
    }

    public function test_parse_rate_limit_error_detects_daily_limit(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'limit_rpd: Rate limit exceeded',
                'metadata' => [
                    'headers' => [
                        'X-RateLimit-Reset' => (string) ((time() + 3600) * 1000), // ms timestamp
                    ],
                ],
            ],
        ]);

        $info = $this->adapter->parseRateLimitError($response);

        $this->assertTrue($info['is_daily_limit']);
    }

    public function test_parse_rate_limit_error_detects_minute_limit(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'limit_rpm: Rate limit exceeded',
            ],
        ]);

        $info = $this->adapter->parseRateLimitError($response);

        $this->assertFalse($info['is_daily_limit']);
    }

    public function test_is_recoverable_error_returns_true_for_server_errors(): void
    {
        foreach ([500, 502, 503] as $status) {
            $response = $this->createMockResponse($status, []);
            $this->assertTrue($this->adapter->isRecoverableError($response));
        }
    }

    public function test_is_recoverable_error_returns_true_for_429_minute_limit(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'limit_rpm: Rate limit exceeded',
            ],
        ]);

        $this->assertTrue($this->adapter->isRecoverableError($response));
    }

    public function test_is_recoverable_error_returns_false_for_429_daily_limit(): void
    {
        $response = $this->createMockResponse(429, [
            'error' => [
                'message' => 'limit_rpd: Daily rate limit exceeded',
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
