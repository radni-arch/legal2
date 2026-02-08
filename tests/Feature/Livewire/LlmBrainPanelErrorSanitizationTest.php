<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\LlmBrainPanel;
use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LlmBrainPanelErrorSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter state between tests
        RateLimiter::clear('llm-brain:127.0.0.1');
    }

    /** @test */
    public function it_sanitizes_html_in_error_messages()
    {
        // Mock service to return error with HTML/script tags
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Database error: <script>alert("xss")</script> Connection failed with <b>credentials</b> exposed',
            ]);
        $this->app->instance(ReasoningChainService::class, $mock);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions')
            ->call('executeQuery');

        // Verify error is set
        $error = $component->get('error');
        $this->assertNotNull($error, 'Error should be set');

        // Verify HTML tags are stripped
        $this->assertStringNotContainsString('<script>', $error, 'Script tags should be stripped');
        $this->assertStringNotContainsString('</script>', $error, 'Script tags should be stripped');
        $this->assertStringNotContainsString('<b>', $error, 'Bold tags should be stripped');
        $this->assertStringNotContainsString('</b>', $error, 'Bold tags should be stripped');

        // Verify clean text remains
        $this->assertStringContainsString('Database error:', $error, 'Clean text should remain');
        $this->assertStringContainsString('Connection failed', $error, 'Clean text should remain');
    }

    /** @test */
    public function it_truncates_long_error_messages()
    {
        // Create a very long error message (1000 characters)
        $longError = 'Database connection failed: ' . str_repeat('This is a very long error message with lots of details. ', 20);
        $this->assertGreaterThan(500, strlen($longError), 'Setup: Error should be > 500 chars');

        // Mock service to return very long error
        $mock = Mockery::mock(ReasoningChainService::class);
        $mock->shouldReceive('executeReasoningChain')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => $longError,
            ]);
        $this->app->instance(ReasoningChainService::class, $mock);

        $component = Livewire::test(LlmBrainPanel::class)
            ->set('naturalQuery', 'Find decisions')
            ->call('executeQuery');

        // Verify error is set
        $error = $component->get('error');
        $this->assertNotNull($error, 'Error should be set');

        // Verify error is truncated to max 500 characters
        $this->assertLessThanOrEqual(500, strlen($error), 'Error should be truncated to max 500 characters');

        // Verify it ends with ellipsis if truncated
        if (strlen($longError) > 500) {
            $this->assertStringEndsWith('...', $error, 'Truncated error should end with ellipsis');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
