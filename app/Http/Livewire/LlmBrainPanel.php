<?php

namespace App\Http\Livewire;

use App\Services\Graph\ReasoningChainService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class LlmBrainPanel extends Component
{
    /**
     * Natural language query input
     */
    public string $naturalQuery = '';

    /**
     * Query results
     */
    public ?array $queryResult = null;

    /**
     * Generated Cypher query
     */
    public ?string $generatedCypher = null;

    /**
     * Query explanation
     */
    public ?string $explanation = null;

    /**
     * Loading state
     */
    public bool $loading = false;

    /**
     * Error message
     */
    public ?string $error = null;

    /**
     * Active mode: query, chat, or reasoning
     */
    public string $mode = 'query';

    /**
     * Query history (max 10 items, most recent first)
     */
    public array $queryHistory = [];

    /**
     * Chat message input
     */
    public string $chatMessage = '';

    /**
     * Chat conversation history
     */
    public array $chatMessages = [];

    /**
     * Trace ID for reasoning chains
     */
    public ?string $traceId = null;

    /**
     * Duration in milliseconds for reasoning chains
     */
    public ?int $durationMs = null;

    /**
     * Example queries for quick access
     */
    public array $examples = [
        [
            'query' => 'Find Supreme Court decisions citing ZKP Article 9',
            'description' => 'Search by law citation',
        ],
        [
            'query' => 'Find contradicting decisions about proportionality',
            'description' => 'Contradiction detection',
        ],
        [
            'query' => 'Find binding precedents through citation chains',
            'description' => 'Multi-hop reasoning',
        ],
        [
            'query' => 'Find decisions affected by law amendment in 2023',
            'description' => 'Temporal impact analysis',
        ],
    ];

    /**
     * Execute natural language query
     */
    public function executeQuery(): void
    {
        // Rate limiting FIRST - prevent DoS attacks via validation
        $key = 'llm-brain:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many requests. Please wait before trying again.';
            return;
        }
        RateLimiter::hit($key, 60);

        // Validate input - empty check
        if (empty(trim($this->naturalQuery))) {
            $this->error = 'Please enter a query';
            return;
        }

        // Sanitize input BEFORE length validation
        $sanitizedQuery = $this->sanitizeQuery($this->naturalQuery);

        // Validate length AFTER sanitization
        if (strlen($sanitizedQuery) > 2000) {
            $this->error = 'Query must not exceed 2000 characters.';
            return;
        }

        // Reset state
        $this->error = null;
        $this->queryResult = null;
        $this->generatedCypher = null;
        $this->explanation = null;
        $this->loading = true;

        try {
            /** @var ReasoningChainService $reasoningChain */
            $reasoningChain = app(ReasoningChainService::class);

            $result = $reasoningChain->executeReasoningChain($sanitizedQuery);

            if ($result['success']) {
                // Add to history (most recent first, max 10)
                array_unshift($this->queryHistory, [
                    'query' => $sanitizedQuery,
                    'timestamp' => now()->toIso8601String(),
                    'result_count' => count($result['results'] ?? []),
                ]);
                $this->queryHistory = array_slice($this->queryHistory, 0, 10);

                $this->queryResult = $result['results'];
                $this->generatedCypher = $result['cypher_query'];
                $this->explanation = $result['explanation'];
            } else {
                $this->error = $this->sanitizeError($result['error'] ?? 'Query execution failed');
            }
        } catch (\Exception $e) {
            // Log internal error details for debugging
            Log::error('LlmBrainPanel query execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query_length' => strlen($sanitizedQuery),
            ]);

            // Show generic error to user - do not expose internal details
            $this->error = 'An error occurred while processing your query. Please try again.';
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Sanitize user query with multi-layer approach
     *
     * Security layers:
     * 1. Decode HTML entities (prevents encoded tag bypass)
     * 2. Strip all HTML tags
     * 3. Remove dangerous Cypher injection patterns
     *
     * @param string $query Raw user input
     * @return string Sanitized query safe for processing
     */
    private function sanitizeQuery(string $query): string
    {
        // Layer 1: Decode HTML entities first
        // This prevents attacks like &lt;script&gt; bypassing strip_tags
        $decoded = html_entity_decode($query, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Layer 2: Strip all HTML tags
        $stripped = strip_tags($decoded);

        // Layer 3: Remove dangerous Cypher injection patterns
        // Pattern matches whole-word dangerous Cypher keywords (case-insensitive)
        $dangerousPatterns = [
            '/\bMATCH\b/i',
            '/\bCREATE\b/i',
            '/\bDELETE\b/i',
            '/\bDETACH\b/i',
            '/\bMERGE\b/i',
            '/\bSET\b/i',
            '/\bREMOVE\b/i',
            '/\bDROP\b/i',
            '/\bCALL\b/i',
        ];

        $sanitized = preg_replace($dangerousPatterns, '', $stripped);

        return trim($sanitized);
    }

    /**
     * Sanitize error message for display
     *
     * @param string $message Error message to sanitize
     * @return string Sanitized error message safe for display
     */
    protected function sanitizeError(string $message): string
    {
        // Strip HTML tags
        $sanitized = strip_tags($message);

        // Truncate to max 500 characters
        if (strlen($sanitized) > 500) {
            $sanitized = substr($sanitized, 0, 497) . '...';
        }

        return $sanitized;
    }

    /**
     * Clear results and reset state
     */
    public function clearResults(): void
    {
        $this->queryResult = null;
        $this->generatedCypher = null;
        $this->explanation = null;
        $this->error = null;
    }

    /**
     * Use an example query
     */
    public function useExample(int $index): void
    {
        if (isset($this->examples[$index])) {
            $this->naturalQuery = $this->examples[$index]['query'];
            $this->clearResults();
        }
    }

    /**
     * Rerun a query from history
     */
    public function rerunFromHistory(int $index): void
    {
        if (isset($this->queryHistory[$index])) {
            $this->naturalQuery = $this->queryHistory[$index]['query'];
            $this->executeQuery();
        }
    }

    /**
     * Execute reasoning chain query
     */
    public function executeReasoningQuery(): void
    {
        if (empty(trim($this->naturalQuery))) {
            $this->error = 'Please enter a query';
            return;
        }

        $this->error = null;
        $this->queryResult = null;
        $this->generatedCypher = null;
        $this->explanation = null;
        $this->traceId = null;
        $this->durationMs = null;
        $this->loading = true;

        try {
            $reasoningChain = app(ReasoningChainService::class);
            $result = $reasoningChain->executeReasoningChain(strip_tags($this->naturalQuery));

            if ($result['success']) {
                $this->queryResult = $result['results'];
                $this->generatedCypher = $result['cypher_query'];
                $this->explanation = $result['explanation'];
                $this->traceId = $result['trace_id'] ?? null;
                $this->durationMs = $result['duration_ms'] ?? null;
            } else {
                $this->error = $this->sanitizeError($result['error'] ?? 'Reasoning chain failed');
            }
        } catch (\Exception $e) {
            Log::error('LlmBrainPanel reasoning query failed', ['error' => $e->getMessage()]);
            $this->error = 'An error occurred while processing your query. Please try again.';
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Send a chat message to the legal assistant
     */
    public function sendChatMessage(): void
    {
        // Rate limiting FIRST - prevent DoS attacks
        $key = 'llm-brain-chat:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->chatMessages[] = ['role' => 'system', 'content' => 'Too many messages. Please wait.'];
            return;
        }
        RateLimiter::hit($key, 60);

        // Validate input - empty check
        if (empty(trim($this->chatMessage))) {
            return;
        }

        // Sanitize input
        $userMessage = strip_tags($this->chatMessage);

        // Add user message to chat
        $this->chatMessages[] = ['role' => 'user', 'content' => $userMessage];

        // Clear input field
        $this->chatMessage = '';

        try {
            /** @var \App\Services\OpenAIService $openai */
            $openai = app(\App\Services\OpenAIService::class);

            $systemPrompt = 'You are a Croatian legal expert assistant. Answer questions about Croatian law, court decisions, and legal concepts. Be concise and accurate.';

            // Build messages array with system prompt + chat history
            $messages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $this->chatMessages)
            );

            // Call OpenAI
            $response = $openai->chat($messages, config('services.openai.reasoning_model', 'gpt-4o'));

            // Add assistant response to chat
            $this->chatMessages[] = ['role' => 'assistant', 'content' => $response['content']];
        } catch (\Exception $e) {
            // Log error
            Log::error('LlmBrainPanel chat failed', ['error' => $e->getMessage()]);

            // Show generic error to user
            $this->chatMessages[] = ['role' => 'system', 'content' => 'An error occurred. Please try again.'];
        }
    }

    /**
     * Clear chat history
     */
    public function clearChat(): void
    {
        $this->chatMessages = [];
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.llm-brain-panel');
    }
}
