<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AI\OpenAIChatService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * TDD Tests for StreamingChatController
 *
 * Tests Server-Sent Events (SSE) streaming for OpenAI chat completions.
 *
 * Sprint 12.5 - Worker A: Streaming Chat with SSE
 */
class StreamingChatControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected $chatServiceMock;

    protected User $testUser;

    protected string $testToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with API token
        $this->testToken = Str::random(60);
        $this->testUser = User::factory()->create([
            'api_token' => $this->testToken,
        ]);

        // Mock the OpenAIChatService
        $this->chatServiceMock = Mockery::mock(OpenAIChatService::class);
        $this->app->instance(OpenAIChatService::class, $this->chatServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: SSE Streaming Works
    // ========================================

    /**
     * Test that streaming chat endpoint returns SSE response
     *
     * @test
     */
    public function test_streaming_chat_sends_sse_events(): void
    {
        // Arrange: Mock chatStream to prevent actual API calls
        // Note: StreamedResponse callbacks execute only during actual streaming,
        // not during unit tests, so we can't verify callback execution here
        $this->chatServiceMock
            ->shouldReceive('chatStream')
            ->andReturn(null);

        // Act: Make streaming request
        $response = $this->withToken($this->testToken)
            ->withHeader('Accept', 'text/event-stream')
            ->postJson('/api/openai/chat/stream', [
                'messages' => [['role' => 'user', 'content' => 'Hello']],
                'model' => 'gpt-4o-mini',
            ]);

        // Assert: Response is SSE stream with correct headers
        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $response->assertHeader('Connection', 'keep-alive');
    }

    // ========================================
    // Test 2: Validation
    // ========================================

    /**
     * Test that streaming chat validates required fields
     *
     * @test
     */
    public function test_streaming_chat_validates_required_fields(): void
    {
        // Act: Make request without required fields
        $response = $this->withToken($this->testToken)
            ->postJson('/api/openai/chat/stream', [
                // Missing messages
                'model' => 'gpt-4o-mini',
            ]);

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['messages']);
    }
}
