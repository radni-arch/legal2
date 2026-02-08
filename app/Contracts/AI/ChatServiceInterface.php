<?php

namespace App\Contracts\AI;

/**
 * Contract for chat/completion services
 */
interface ChatServiceInterface
{
    /**
     * Send a chat completion request
     *
     * @param  array  $messages  Chat messages (role, content)
     * @param  string  $model  Model name (gpt-4o, gpt-4o-mini, etc.)
     * @param  array  $options  Additional options (temperature, max_tokens, etc.)
     * @return array Response with choices, usage, etc.
     */
    public function chat(array $messages, string $model = 'gpt-4o', array $options = []): array;

    /**
     * Send a chat completion request with streaming
     *
     * @param  callable  $callback  Callback for each chunk
     */
    public function chatStream(array $messages, string $model, array $options, callable $callback): void;

    /**
     * Get available models
     *
     * @return array List of available models
     */
    public function getAvailableModels(): array;
}
