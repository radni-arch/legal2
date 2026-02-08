<?php

namespace App\Services\AI;

use App\Contracts\AI\AnalysisServiceInterface;
use App\Contracts\AI\ChatServiceInterface;
use App\Contracts\AI\EmbeddingServiceInterface;

/**
 * OpenAI Services Orchestrator
 *
 * Implements the Facade pattern to provide a unified interface to specialized
 * OpenAI services (chat, embeddings, analysis). This orchestrator delegates
 * requests to the appropriate specialized service based on the operation.
 *
 * Benefits:
 * - Single point of entry for all OpenAI operations
 * - Encapsulates service dependencies
 * - Simplifies testing through dependency injection
 * - Maintains backward compatibility with existing code
 *
 * @see \App\Contracts\AI\ChatServiceInterface
 * @see \App\Contracts\AI\EmbeddingServiceInterface
 * @see \App\Contracts\AI\AnalysisServiceInterface
 */
class OpenAIOrchestrator
{
    /**
     * Create a new OpenAIOrchestrator instance
     *
     * @param  ChatServiceInterface  $chat  Chat completion service
     * @param  EmbeddingServiceInterface  $embedding  Embedding generation service
     * @param  AnalysisServiceInterface  $analysis  Text analysis service
     */
    public function __construct(
        protected ChatServiceInterface $chat,
        protected EmbeddingServiceInterface $embedding,
        protected AnalysisServiceInterface $analysis
    ) {}

    // ========================================
    // Chat Methods (delegate to ChatService)
    // ========================================

    /**
     * Send a chat completion request
     *
     * @param  array  $messages  Chat messages
     * @param  string  $model  Model name
     * @param  array  $options  Additional options
     * @return array Response
     */
    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array
    {
        return $this->chat->chat($messages, $model, $options);
    }

    /**
     * Send a chat completion request with streaming
     */
    public function chatStream(array $messages, string $model = 'gpt-4o', array $options = [], ?callable $callback = null): void
    {
        $this->chat->chatStream($messages, $model, $options, $callback ?? function () {});
    }

    /**
     * Get available chat models
     */
    public function getAvailableModels(): array
    {
        return $this->chat->getAvailableModels();
    }

    // ========================================
    // Embedding Methods (delegate to EmbeddingService)
    // ========================================

    /**
     * Generate embedding for text
     *
     * @param  string  $text  Text to embed
     * @param  string  $model  Embedding model
     * @return array Embedding vector
     */
    public function embed(string $text, string $model = 'text-embedding-3-small'): array
    {
        return $this->embedding->embed($text, $model);
    }

    /**
     * Generate embeddings for multiple texts
     *
     * @param  array  $texts  Array of texts
     * @return array Array of embedding vectors
     */
    public function batchEmbed(array $texts, string $model = 'text-embedding-3-small'): array
    {
        return $this->embedding->batchEmbed($texts, $model);
    }

    /**
     * Get embedding dimensions for a model
     *
     * @return int Dimension count
     */
    public function getEmbeddingDimensions(string $model): int
    {
        return $this->embedding->getEmbeddingDimensions($model);
    }

    /**
     * Legacy method: Generate embeddings (maps to embed())
     */
    public function embeddings(string|array $input, ?string $model = null, array $options = []): array
    {
        $model = $model ?? 'text-embedding-3-small';

        if (is_array($input)) {
            return $this->batchEmbed($input, $model);
        }

        return $this->embed($input, $model);
    }

    // ========================================
    // Analysis Methods (delegate to AnalysisService)
    // ========================================

    /**
     * Analyze legal text
     *
     * @param  string  $text  Legal text to analyze
     * @param  array  $options  Analysis options
     * @return array Analysis results
     */
    public function analyzeLegalText(string $text, array $options = []): array
    {
        return $this->analysis->analyzeLegalText($text, $options);
    }

    /**
     * Summarize text
     *
     * @param  string  $text  Text to summarize
     * @param  int  $maxLength  Maximum summary length
     * @return string Summary
     */
    public function summarize(string $text, int $maxLength = 500): string
    {
        return $this->analysis->summarize($text, $maxLength);
    }

    /**
     * Extract structured information from text
     *
     * @param  array  $schema  Expected output schema
     * @return array Extracted information
     */
    public function extractStructuredData(string $text, array $schema): array
    {
        return $this->analysis->extractStructuredData($text, $schema);
    }
}
