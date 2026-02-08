<?php

namespace Tests\Doubles;

use App\Services\OpenAIService;

/**
 * Fake OpenAI Service for testing
 *
 * Provides predictable responses without making external API calls.
 * Use this in integration tests to avoid real API calls, timeouts, and costs.
 */
class FakeOpenAIService extends OpenAIService
{
    private array $chatResponses = [];

    private array $embeddingResponses = [];

    private int $chatCallCount = 0;

    private int $embeddingCallCount = 0;

    public function __construct()
    {
        // Skip parent constructor to avoid loading config/circuit breaker
    }

    /**
     * Queue a chat response to be returned on next chat() call
     */
    public function queueChatResponse(array $response): self
    {
        $this->chatResponses[] = $response;

        return $this;
    }

    /**
     * Queue an embedding response to be returned on next createEmbedding() call
     */
    public function queueEmbeddingResponse(array $embedding): self
    {
        $this->embeddingResponses[] = $embedding;

        return $this;
    }

    /**
     * Override chat method to return fake responses
     */
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $this->chatCallCount++;

        if (! empty($this->chatResponses)) {
            return array_shift($this->chatResponses);
        }

        // Handle case where all parameters are passed in first argument
        // (matching real OpenAIService behavior)
        if (isset($messages['messages'])) {
            $options = array_merge($options, $messages);
            $messages = $options['messages'];
            $model = $options['model'] ?? $model;
        }

        return $this->defaultChatResponse($messages, $model, $options);
    }

    /**
     * Override embeddings method to return fake embeddings
     */
    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $this->embeddingCallCount++;

        if (! empty($this->embeddingResponses)) {
            $response = array_shift($this->embeddingResponses);

            return [
                'data' => [['embedding' => $response]],
                'model' => $model ?? 'text-embedding-3-small',
                'usage' => ['total_tokens' => is_string($input) ? str_word_count($input) : 100],
            ];
        }

        return $this->defaultEmbeddingResponse($input, $model);
    }

    /**
     * Override createEmbedding method to return fake embedding vector
     */
    public function createEmbedding(string $text, ?string $model = null): array
    {
        if (! empty($this->embeddingResponses)) {
            return array_shift($this->embeddingResponses);
        }

        // Return standard 1536-dimension embedding vector with small random values
        return array_fill(0, 1536, 0.1);
    }

    /**
     * Get number of times chat() was called
     */
    public function getChatCallCount(): int
    {
        return $this->chatCallCount;
    }

    /**
     * Get number of times createEmbedding() or embeddings() was called
     */
    public function getEmbeddingCallCount(): int
    {
        return $this->embeddingCallCount;
    }

    /**
     * Reset all counters and queued responses
     */
    public function reset(): void
    {
        $this->chatResponses = [];
        $this->embeddingResponses = [];
        $this->chatCallCount = 0;
        $this->embeddingCallCount = 0;
    }

    /**
     * Default chat response when no responses are queued
     */
    private function defaultChatResponse(array $messages, ?string $model, array $options): array
    {
        $responseFormat = $options['response_format']['type'] ?? null;

        // Detect if this is an unclear/uncertain narrative
        // Look for "Unclear legal situation" pattern which indicates test case for low confidence
        $userMessage = '';
        foreach ($messages as $message) {
            if (isset($message['content']) && isset($message['role']) && $message['role'] === 'user') {
                $userMessage = strtolower($message['content']);
                break;
            }
        }
        // Return low confidence if the narrative contains "unclear legal situation"
        $isUnclear = str_contains($userMessage, 'unclear legal situation');

        $content = $responseFormat === 'json_object'
            ? json_encode([
                'legal_area' => 'contract',
                'confidence' => $isUnclear ? 0.45 : 0.87,
                'structured_facts' => [
                    'parties' => [
                        ['name' => 'Client', 'role' => 'plaintiff'],
                        ['name' => 'Opponent', 'role' => 'defendant'],
                    ],
                    'legal_issues' => [
                        ['area_of_law' => 'contract', 'issue' => 'Breach of contract'],
                    ],
                    'events' => [
                        ['date' => '2024-03-15', 'description' => 'Contract signed'],
                    ],
                    'evidence' => [
                        [
                            'description' => 'Contract document',
                            'strength' => 'strong',
                            'availability' => 'needs_discovery',
                        ],
                    ],
                    'summary' => 'Test case generated by FakeOpenAIService',
                    'facts_favorable_to_plaintiff' => [
                        'Contract was signed on agreed date',
                    ],
                    'facts_favorable_to_defendant' => [],
                    'disputed_facts' => [],
                    'procedural_posture' => [
                        'stage' => 'pre-filing',
                        'court' => null,
                        'jurisdiction' => null,
                        'deadlines' => [],
                    ],
                ],
                'analysis' => 'Default analysis',
                'conclusion' => 'Default conclusion',
            ])
            : 'This is a default response from FakeOpenAIService';

        return [
            'id' => 'chatcmpl-fake-'.uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model ?? 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
            ],
        ];
    }

    /**
     * Default embedding response when no responses are queued
     */
    private function defaultEmbeddingResponse(string|array $input, ?string $model): array
    {
        $inputs = is_array($input) ? $input : [$input];
        $data = [];

        foreach ($inputs as $index => $text) {
            $data[] = [
                'object' => 'embedding',
                'embedding' => array_fill(0, 1536, 0.1), // Standard embedding dimension
                'index' => $index,
            ];
        }

        return [
            'object' => 'list',
            'data' => $data,
            'model' => $model ?? 'text-embedding-3-small',
            'usage' => [
                'prompt_tokens' => is_string($input) ? str_word_count($input) : 100,
                'total_tokens' => is_string($input) ? str_word_count($input) : 100,
            ],
        ];
    }
}
