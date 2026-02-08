<?php

namespace App\Contracts\Services;

/**
 * McpToOpenAIBridgeInterface
 *
 * Bridge service that exposes MCP tools as OpenAI-compatible functions.
 * Provides:
 * - OpenAI function format conversion
 * - Tool execution with MCP backend
 * - Tool definition generation for OpenAI API
 */
interface McpToOpenAIBridgeInterface
{
    /**
     * Get all available tools in OpenAI function format
     *
     * Returns array of function definitions that can be passed to OpenAI's
     * chat completion API for function calling.
     *
     * @return array Array of OpenAI-compatible function definitions
     */
    public function getOpenAIFunctions(): array;

    /**
     * Execute a tool call from OpenAI
     *
     * Receives a tool name and arguments from OpenAI function calling,
     * executes the corresponding MCP tool, and returns the result.
     *
     * @param  string  $toolName  Tool identifier (e.g., 'odluke_search', 'law_articles_search')
     * @param  array  $arguments  Tool arguments from OpenAI
     * @return array Result with 'success', 'content', and 'error' keys
     */
    public function executeTool(string $toolName, array $arguments): array;

    /**
     * Get tool definitions for OpenAI /v1/tools endpoint
     *
     * Returns tools in the format expected by OpenAI's tools API endpoint.
     *
     * @return array Tool definitions with 'object' and 'data' keys
     */
    public function getToolDefinitions(): array;

    /**
     * Process a chat completion request with tool calls
     *
     * Prepares tool configuration for OpenAI chat completion request.
     *
     * @param  array  $messages  Chat messages
     * @param  array  $tools  Available tools
     * @param  string|null  $model  Model identifier
     * @return array Configuration with 'tools' and 'tool_choice'
     */
    public function processChatWithTools(array $messages, array $tools, ?string $model = null): array;
}
