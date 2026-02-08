<?php

namespace App\Contracts\Research;

/**
 * Interface for executing searches across all available corpora
 *
 * This component is responsible for:
 * - Executing planned search actions
 * - Routing searches to the appropriate services (Law, Decision, Case)
 * - Handling graph queries and web fetches
 * - Providing error handling and fallbacks
 */
interface SearchExecutorInterface
{
    /**
     * Execute searches across all corpora
     *
     * @param  array  $questions  Array of search actions to execute
     *                            Each action has:
     *                            - tool: Tool name
     *                            - params: Parameters for the tool
     * @param  array  $options  Execution options:
     *                          - parallel: Execute searches in parallel (default: false)
     *                          - timeout: Timeout in seconds per search
     *                          - retries: Number of retries on failure
     * @return array Results from all searches with structure:
     *               - tool: Tool that was executed
     *               - params: Parameters used
     *               - result: Search results
     *               - success: Whether the search succeeded
     *               - error: Error message if failed
     */
    public function execute(array $questions, array $options = []): array;

    /**
     * Search specific corpus
     *
     * @param  string  $corpus  Corpus name (e.g., 'law', 'decision', 'case')
     * @param  string  $query  Search query
     * @param  array  $options  Search options:
     *                          - search_type: 'vector', 'keyword', 'hybrid'
     *                          - limit: Number of results
     *                          - filters: Additional filters
     * @return array Search results
     */
    public function searchCorpus(string $corpus, string $query, array $options = []): array;

    /**
     * Execute a single action
     *
     * @param  string  $tool  Tool name
     * @param  array  $params  Tool parameters
     * @return array Search result
     */
    public function executeAction(string $tool, array $params): array;
}
