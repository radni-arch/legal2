<?php

namespace App\Exceptions;

use Exception;

class AgentException extends Exception
{
    public const AGENT_NOT_FOUND = 7001;

    public const INITIALIZATION_FAILED = 7002;

    public const EXECUTION_FAILED = 7003;

    public const TIMEOUT = 7004;

    public const BUDGET_EXCEEDED = 7005;

    public const TOOL_EXECUTION_FAILED = 7006;

    public const EVALUATION_FAILED = 7007;

    public const CHECKPOINT_FAILED = 7008;

    public const CANCELLATION_FAILED = 7009;

    public const RESUME_FAILED = 7010;

    public const GRAPH_QUERY_FAILED = 7011;

    public const WEB_FETCH_FAILED = 7012;

    public const MEMORY_SAVE_FAILED = 7013;

    public const MEMORY_RETRIEVAL_FAILED = 7014;

    public const SEARCH_FAILED = 7015;

    public const EMBEDDING_GENERATION_FAILED = 7016;

    public const UNEXPECTED_ERROR = 7999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Agent exception occurred', [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);

        return true;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match ($this->getCode()) {
            self::AGENT_NOT_FOUND => 'AI agent not found.',
            self::INITIALIZATION_FAILED => 'Failed to initialize AI agent.',
            self::EXECUTION_FAILED => 'AI agent execution failed.',
            self::TIMEOUT => 'AI agent execution timed out.',
            self::BUDGET_EXCEEDED => 'AI agent budget exceeded.',
            self::TOOL_EXECUTION_FAILED => 'AI agent tool execution failed.',
            self::EVALUATION_FAILED => 'AI agent evaluation failed.',
            self::CHECKPOINT_FAILED => 'Failed to checkpoint AI agent state.',
            self::CANCELLATION_FAILED => 'Failed to cancel AI agent.',
            self::RESUME_FAILED => 'Failed to resume AI agent.',
            self::GRAPH_QUERY_FAILED => 'Graph database query failed.',
            self::WEB_FETCH_FAILED => 'Failed to fetch web content.',
            self::MEMORY_SAVE_FAILED => 'Failed to save agent memory.',
            self::MEMORY_RETRIEVAL_FAILED => 'Failed to retrieve agent memory.',
            self::SEARCH_FAILED => 'Search operation failed.',
            self::EMBEDDING_GENERATION_FAILED => 'Failed to generate embeddings.',
            default => 'AI agent operation failed.',
        };
    }
}
