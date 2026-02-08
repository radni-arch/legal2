<?php

namespace App\Contracts\Services\Mcp;

/**
 * InternalMcpClientInterface
 *
 * Provides direct in-process access to MCP tools without HTTP overhead.
 * Used when running agents via dashboard or artisan commands for:
 * - Efficient local tool execution
 * - Reliable in-process communication
 * - Tool discovery and metadata
 */
interface InternalMcpClientInterface
{
    /**
     * Call an MCP tool directly (bypassing HTTP)
     *
     * Executes an MCP tool with the given arguments and returns the result.
     * Handles tool routing, argument validation, and error responses.
     *
     * Supported tools:
     * - odluke-search: Search court decisions
     * - odluke-meta: Get decision metadata
     * - odluke-download: Download decisions
     * - law-articles-search: Search law articles
     * - law-article-by-id: Get law article by ID
     *
     * @param  string  $toolName  MCP tool name (e.g., 'odluke-search')
     * @param  array  $arguments  Tool-specific arguments
     * @return array MCP result with 'content' and 'isError' keys
     */
    public function callTool(string $toolName, array $arguments = []): array;

    /**
     * List all available MCP tools
     *
     * Returns tool definitions with names, descriptions, and input schemas.
     *
     * @return array Tool list with 'tools' array containing tool metadata
     */
    public function listTools(): array;

    /**
     * Get information about this MCP client
     *
     * Returns client metadata including name, version, transport type,
     * and available tools.
     *
     * @return array Client info with 'name', 'version', 'transport', 'tools'
     */
    public function getInfo(): array;
}
