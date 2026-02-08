<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OpenAIException;
use App\Http\Controllers\Controller;
use App\Services\AI\OpenAIChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streaming Chat Controller
 *
 * Handles Server-Sent Events (SSE) streaming for OpenAI chat completions.
 * Provides real-time streaming responses for chat interactions.
 *
 * Sprint 12.5 - Worker A: Streaming Chat with SSE
 */
class StreamingChatController extends Controller
{
    public function __construct(
        protected OpenAIChatService $chatService
    ) {}

    /**
     * Stream a chat completion using Server-Sent Events
     *
     * POST /api/openai/chat/stream
     *
     * Request Body:
     * {
     *   "messages": [{"role": "user", "content": "Hello"}],
     *   "model": "gpt-4o-mini",
     *   "temperature": 0.7,
     *   "max_tokens": 1000
     * }
     *
     * Response: text/event-stream (SSE)
     * data: {"choices":[{"delta":{"content":"Hello"}}]}
     * data: {"choices":[{"delta":{"content":" world"}}]}
     * data: [DONE]
     */
    public function stream(Request $request): StreamedResponse
    {
        // Validate request
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:system,user,assistant'],
            'messages.*.content' => ['required', 'string'],
            'model' => ['sometimes', 'string'],
            'temperature' => ['sometimes', 'numeric', 'between:0,2'],
            'max_tokens' => ['sometimes', 'integer', 'min:1', 'max:4096'],
        ]);

        $messages = $validated['messages'];
        $model = $validated['model'] ?? 'gpt-4o-mini';
        $options = [
            'temperature' => $validated['temperature'] ?? 0.7,
            'max_tokens' => $validated['max_tokens'] ?? 1000,
        ];

        // Create SSE streamed response
        return response()->stream(
            function () use ($messages, $model, $options) {
                // Set execution time limit for streaming
                set_time_limit(300); // 5 minutes

                // Send initial connection event
                echo 'data: '.json_encode(['status' => 'connected'])."\n\n";
                ob_flush();
                flush();

                try {
                    // Stream chat completion
                    $this->chatService->chatStream(
                        $messages,
                        $model,
                        $options,
                        function (array $chunk) {
                            // Send SSE formatted chunk
                            echo 'data: '.json_encode($chunk)."\n\n";
                            ob_flush();
                            flush();
                        }
                    );

                    // Send completion signal
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();

                } catch (OpenAIException $e) {
                    // Send error event
                    $errorData = [
                        'error' => [
                            'message' => $e->getUserMessage(),
                            'code' => $e->getCode(),
                        ],
                    ];

                    echo 'data: '.json_encode($errorData)."\n\n";
                    ob_flush();
                    flush();

                    Log::error('Streaming chat error', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ]);
                } catch (\Throwable $e) {
                    // Send unexpected error event
                    $errorData = [
                        'error' => [
                            'message' => 'An unexpected error occurred during streaming.',
                            'code' => 500,
                        ],
                    ];

                    echo 'data: '.json_encode($errorData)."\n\n";
                    ob_flush();
                    flush();

                    Log::error('Unexpected streaming error', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disable nginx buffering
            ]
        );
    }
}
