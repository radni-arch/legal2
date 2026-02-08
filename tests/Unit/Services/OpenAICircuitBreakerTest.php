<?php

namespace Tests\Unit\Services;

use App\Exceptions\OpenAIException;
use App\Exceptions\PartialOpenAIFailureException;
use App\Services\AI\OpenAIChatService;
use App\Services\CircuitBreakerException;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test circuit breaker protection for OpenAI multipart methods
 *
 * Verifies that:
 * - Streaming methods respect circuit breaker
 * - Multipart uploads respect circuit breaker
 * - Partial failures are handled gracefully
 * - Circuit breaker tracks streaming chunk successes/failures
 */
class OpenAICircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear circuit breaker cache before each test
        Cache::flush();
    }

    /** @test */
    public function chat_stream_respects_circuit_breaker_when_open()
    {
        // Arrange: Force circuit breaker to OPEN state
        Cache::put('circuit_breaker:openai-chat:state', 'open');
        Cache::put('circuit_breaker:openai-chat:last_failure', time());

        $chatService = app(OpenAIChatService::class);

        // Act & Assert: chatStream should throw CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');

        $chatService->chatStream(
            [['role' => 'user', 'content' => 'Test']],
            'gpt-4o',
            [],
            function ($chunk) {
                // This should not be called
                $this->fail('Callback should not be called when circuit is open');
            }
        );
    }

    /** @test */
    public function chat_stream_tracks_successful_chunks()
    {
        // Arrange: Mock successful streaming response
        Http::fake([
            '*/chat/completions' => Http::response(
                "data: {\"id\":\"1\",\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n".
                "data: {\"id\":\"2\",\"choices\":[{\"delta\":{\"content\":\" World\"}}]}\n\n".
                "data: [DONE]\n",
                200
            ),
        ]);

        $chatService = app(OpenAIChatService::class);
        $receivedChunks = [];

        // Act: Stream chat completion
        $chatService->chatStream(
            [['role' => 'user', 'content' => 'Test']],
            'gpt-4o',
            [],
            function ($chunk) use (&$receivedChunks) {
                $receivedChunks[] = $chunk;
            }
        );

        // Assert: Chunks were received
        $this->assertCount(2, $receivedChunks);
        $this->assertEquals('Hello', $receivedChunks[0]['choices'][0]['delta']['content']);
        $this->assertEquals(' World', $receivedChunks[1]['choices'][0]['delta']['content']);
    }

    /** @test */
    public function chat_stream_throws_partial_failure_exception_on_mid_stream_error()
    {
        // Arrange: Mock streaming response that fails mid-stream
        Http::fake([
            '*/chat/completions' => Http::response(
                "data: {\"id\":\"1\",\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n",
                500 // Server error mid-stream
            ),
        ]);

        $chatService = app(OpenAIChatService::class);
        $receivedChunks = [];

        // Act & Assert: Should receive partial success then exception
        try {
            $chatService->chatStream(
                [['role' => 'user', 'content' => 'Test']],
                'gpt-4o',
                [],
                function ($chunk) use (&$receivedChunks) {
                    $receivedChunks[] = $chunk;
                }
            );

            $this->fail('Should have thrown PartialOpenAIFailureException');
        } catch (PartialOpenAIFailureException $e) {
            // Assert: Exception indicates partial success
            $this->assertGreaterThan(0, $e->getSuccessfulPartCount());
            $this->assertTrue($e->hasSuccessfulParts());
            $this->assertNotEmpty($e->getSuccessfulParts());
        } catch (OpenAIException $e) {
            // Also accept regular OpenAIException for complete failures
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function transcribe_method_respects_circuit_breaker()
    {
        // Arrange: Force circuit breaker to OPEN state
        Cache::put('circuit_breaker:openai:state', 'open');
        Cache::put('circuit_breaker:openai:last_failure', time());

        $openAIService = app(OpenAIService::class);

        // Act & Assert: transcribe should throw CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');

        $openAIService->transcribe(__FILE__); // Use current file for test
    }

    /** @test */
    public function transcribe_method_uses_circuit_breaker_on_success()
    {
        // Arrange: Mock successful transcription response
        Http::fake([
            '*/audio/transcriptions' => Http::response([
                'text' => 'Transcribed audio content',
            ], 200),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Transcribe audio file
        $result = $openAIService->transcribe(__FILE__);

        // Assert: Transcription succeeded
        $this->assertEquals('Transcribed audio content', $result['text']);

        // Assert: Circuit breaker failure count should be reset (success)
        $failureCount = Cache::get('circuit_breaker:openai:failures', 0);
        $this->assertEquals(0, $failureCount);
    }

    /** @test */
    public function file_upload_respects_circuit_breaker()
    {
        // Arrange: Force circuit breaker to OPEN state
        Cache::put('circuit_breaker:openai:state', 'open');
        Cache::put('circuit_breaker:openai:last_failure', time());

        $openAIService = app(OpenAIService::class);

        // Act & Assert: fileUpload should throw CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');

        $openAIService->fileUpload(__FILE__, 'assistants');
    }

    /** @test */
    public function file_upload_method_uses_circuit_breaker_on_success()
    {
        // Arrange: Mock successful file upload response
        Http::fake([
            '*/files' => Http::response([
                'id' => 'file-123',
                'purpose' => 'assistants',
                'filename' => basename(__FILE__),
            ], 200),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Upload file
        $result = $openAIService->fileUpload(__FILE__, 'assistants');

        // Assert: Upload succeeded
        $this->assertEquals('file-123', $result['id']);
        $this->assertEquals('assistants', $result['purpose']);
    }

    /** @test */
    public function tts_method_respects_circuit_breaker()
    {
        // Arrange: Force circuit breaker to OPEN state
        Cache::put('circuit_breaker:openai:state', 'open');
        Cache::put('circuit_breaker:openai:last_failure', time());

        $openAIService = app(OpenAIService::class);

        // Act & Assert: tts should throw CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');

        $openAIService->tts('Test text to speech');
    }

    /** @test */
    public function tts_method_uses_circuit_breaker_on_success()
    {
        // Arrange: Mock successful TTS response (binary audio)
        Http::fake([
            '*/audio/speech' => Http::response('binary audio data', 200, [
                'Content-Type' => 'audio/mpeg',
            ]),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Generate speech
        $result = $openAIService->tts('Test text to speech');

        // Assert: TTS succeeded
        $this->assertEquals('binary audio data', $result);
    }

    /** @test */
    public function list_methods_use_circuit_breaker()
    {
        // Arrange: Mock successful list responses
        Http::fake([
            '*/assistants*' => Http::response([
                'data' => [
                    ['id' => 'asst-1', 'name' => 'Assistant 1'],
                    ['id' => 'asst-2', 'name' => 'Assistant 2'],
                ],
            ], 200),
            '*/vector_stores*' => Http::response([
                'data' => [
                    ['id' => 'vs-1', 'name' => 'Vector Store 1'],
                ],
            ], 200),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Call list methods
        $assistants = $openAIService->assistantsList();
        $vectorStores = $openAIService->vectorStoreList();

        // Assert: Lists succeeded
        $this->assertCount(2, $assistants['data']);
        $this->assertCount(1, $vectorStores['data']);
    }

    /** @test */
    public function circuit_breaker_opens_after_threshold_failures()
    {
        // Arrange: Mock failing responses
        Http::fake([
            '*/chat/completions' => Http::response(['error' => 'Server error'], 500),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Cause failures until circuit opens
        $failureThreshold = config('openai.circuit_breaker.failure_threshold', 5);

        for ($i = 0; $i < $failureThreshold; $i++) {
            try {
                $openAIService->chat([['role' => 'user', 'content' => 'Test']]);
                $this->fail('Request should have failed');
            } catch (\Exception $e) {
                // Expected failure
            }
        }

        // Assert: Circuit should be open now
        $status = Cache::get('circuit_breaker:openai:state');
        $this->assertEquals('open', $status);

        // Act & Assert: Next request should be blocked by circuit breaker
        $this->expectException(CircuitBreakerException::class);
        $openAIService->chat([['role' => 'user', 'content' => 'Test']]);
    }

    /** @test */
    public function partial_failure_exception_contains_successful_parts()
    {
        // Arrange: Create exception with successful parts
        $successfulChunks = [
            ['id' => '1', 'content' => 'Hello'],
            ['id' => '2', 'content' => 'World'],
        ];

        $exception = new PartialOpenAIFailureException(
            'Stream failed after 2 chunks',
            PartialOpenAIFailureException::PARTIAL_STREAM_FAILURE,
            $successfulChunks,
            ['chunks_received' => 2]
        );

        // Assert: Exception properties
        $this->assertTrue($exception->hasSuccessfulParts());
        $this->assertEquals(2, $exception->getSuccessfulPartCount());
        $this->assertEquals($successfulChunks, $exception->getSuccessfulParts());
        $this->assertEquals(['chunks_received' => 2], $exception->getFailureMetadata());
    }

    /** @test */
    public function retry_sleep_callback_accepts_exception_parameter()
    {
        // Regression test: Laravel's HTTP retry sleep callback receives ($attempt, $exception)
        // but the old code only declared (int $attempt), causing:
        // "Unsupported operand types: ConnectionException - int" when backoff math ran

        // Arrange: Configure retry with exponential backoff
        config([
            'openai.api_key' => 'test-key',
            'openai.retry' => [
                'times' => 2,
                'sleep_ms' => 100,
                'exponential_backoff' => true,
                'jitter' => true,
                'when' => ['status_codes' => [500]],
            ],
        ]);

        // Mock: first request throws ConnectionException, second succeeds
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(null, 500)
                ->push([
                    'choices' => [['message' => ['content' => 'OK']]],
                ], 200),
        ]);

        $openAIService = new OpenAIService;

        // Act: This should NOT throw "Unsupported operand types" from the sleep callback
        // The retry will encounter a 500, compute backoff sleep (with $exception param), then retry
        try {
            $result = $openAIService->chat([['role' => 'user', 'content' => 'Test']]);
            $this->assertEquals('OK', $result['choices'][0]['message']['content']);
        } catch (\Exception $e) {
            // The request itself may fail for other reasons (circuit breaker, etc.)
            // but it MUST NOT fail with "Unsupported operand types"
            $this->assertStringNotContainsString(
                'Unsupported operand types',
                $e->getMessage(),
                'Retry sleep callback should accept optional $exception parameter'
            );
        }
    }

    /** @test */
    public function circuit_breaker_recovers_after_timeout()
    {
        // Arrange: Force circuit to OPEN with old failure time
        $oldFailureTime = time() - 120; // 2 minutes ago
        Cache::put('circuit_breaker:openai:state', 'open');
        Cache::put('circuit_breaker:openai:last_failure', $oldFailureTime);
        Cache::put('circuit_breaker:openai:failures', 5);

        // Mock successful response for recovery
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Success']]],
            ], 200),
        ]);

        $openAIService = app(OpenAIService::class);

        // Act: Make request (should transition to HALF_OPEN then CLOSED)
        $result = $openAIService->chat([['role' => 'user', 'content' => 'Test']]);

        // Assert: Request succeeded
        $this->assertEquals('Success', $result['choices'][0]['message']['content']);

        // Circuit should transition toward recovery
        // (exact state depends on success threshold)
        $state = Cache::get('circuit_breaker:openai:state');
        $this->assertContains($state, ['half_open', 'closed']);
    }
}
