<?php

namespace App\Services\Research;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * InsightExtractorService
 *
 * LLM-powered extraction of structured legal insights from search results.
 * Extracts key findings with legal citations, relevance scoring, and related concepts.
 */
class InsightExtractorService
{
    public function __construct(
        protected OpenAIService $openai
    ) {}

    /**
     * Extract structured insights from search results
     *
     * @param array $searchResults Search results to analyze
     * @param string $query Original research query for context
     * @return array Array of insights with title, summary, legal_basis, relevance_score, related_concepts
     */
    public function extractInsights(array $searchResults, string $query): array
    {
        // Return empty array immediately if no results (no LLM call needed)
        if (empty($searchResults)) {
            return [];
        }

        $prompt = $this->buildExtractionPrompt($searchResults, $query);

        try {
            $response = $this->openai->chat([
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

            $insightsJson = $response['choices'][0]['message']['content'];
            $data = json_decode($insightsJson, true);

            if (!isset($data['insights']) || !is_array($data['insights'])) {
                throw new \Exception('Invalid insights structure from LLM');
            }

            $tokensUsed = $response['usage']['total_tokens'] ?? 0;

            Log::info('InsightExtractorService: Insights extracted', [
                'query' => $query,
                'insights_count' => count($data['insights']),
                'tokens_used' => $tokensUsed,
            ]);

            // Validate and filter insights
            return $this->validateInsights($data['insights']);

        } catch (\Exception $e) {
            Log::error('InsightExtractorService: Extraction failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function buildExtractionPrompt(array $searchResults, string $query): string
    {
        $resultsFormatted = $this->formatSearchResults($searchResults);

        return <<<PROMPT
**RESEARCH QUERY:**
{$query}

**SEARCH RESULTS:**
{$resultsFormatted}

**YOUR TASK:**
Extract key legal insights from these search results that directly address the research query.

**RESPOND WITH JSON:**
{
    "insights": [
        {
            "title": "Brief insight title",
            "summary": "Detailed summary of the finding",
            "legal_basis": "Legal citation (e.g., ZKP čl. 240, st. 2)",
            "relevance_score": 95,
            "related_concepts": ["concept1", "concept2"]
        }
    ]
}

**REQUIREMENTS:**
- Only extract insights that directly address the query
- Always include specific legal citations
- Relevance score: 0-100 (how relevant to query)
- Include related legal concepts for context
PROMPT;
    }

    protected function getSystemPrompt(): string
    {
        return "You are a legal insight extraction expert. Extract structured, actionable legal insights with proper citations from research results.";
    }

    protected function formatSearchResults(array $results): string
    {
        if (empty($results)) {
            return "No results provided.";
        }

        $formatted = '';
        foreach (array_slice($results, 0, 10) as $idx => $result) {
            $num = $idx + 1;
            $formatted .= "Result {$num}: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        }

        return $formatted;
    }

    /**
     * Validate insights structure
     *
     * Ensures each insight has required fields:
     * - title (string)
     * - summary (string)
     * - legal_basis (string)
     * - relevance_score (int 0-100)
     * - related_concepts (array)
     *
     * Invalid insights are filtered out.
     */
    protected function validateInsights(array $insights): array
    {
        $validated = [];

        foreach ($insights as $insight) {
            // Check required fields
            if (!isset($insight['title']) || !is_string($insight['title']) || empty(trim($insight['title']))) {
                continue;
            }

            if (!isset($insight['summary']) || !is_string($insight['summary']) || empty(trim($insight['summary']))) {
                continue;
            }

            if (!isset($insight['legal_basis']) || !is_string($insight['legal_basis'])) {
                continue;
            }

            if (!isset($insight['relevance_score']) || !is_numeric($insight['relevance_score'])) {
                continue;
            }

            if (!isset($insight['related_concepts']) || !is_array($insight['related_concepts'])) {
                continue;
            }

            // Ensure relevance_score is in valid range
            $score = (int) $insight['relevance_score'];
            if ($score < 0 || $score > 100) {
                continue;
            }

            // Add validated insight with normalized fields
            $validated[] = [
                'title' => trim($insight['title']),
                'summary' => trim($insight['summary']),
                'legal_basis' => trim($insight['legal_basis']),
                'relevance_score' => $score,
                'related_concepts' => array_values($insight['related_concepts']),
            ];
        }

        return $validated;
    }
}
