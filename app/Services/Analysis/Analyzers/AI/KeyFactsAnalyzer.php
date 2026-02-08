<?php

namespace App\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class KeyFactsAnalyzer implements DocumentAnalyzerInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function type(): string
    {
        return DocumentAnalysis::TYPE_KEY_FACTS;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_AI_BASIC;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $systemPrompt = <<<PROMPT
You are a Croatian legal document analyst specializing in evidence analysis. Your task is to extract discrete factual claims from the document that can be used for contradiction detection in multi-document analysis.

For each factual claim, extract:
- **id**: Unique identifier (F1, F2, F3, etc.)
- **statement**: The factual claim in clear language
- **source**: Where in the document this claim appears (witness name, page number, paragraph)
- **category**: Type of fact (location, time, action, identity, relationship, physical_evidence, testimony, procedural)
- **certainty**: How certain is this claim? (established, claimed, disputed, inferred)
- **date_referenced**: If the fact involves a date, provide it in ISO format (YYYY-MM-DD)
- **actors**: Who is involved in this fact

Focus on facts that could potentially contradict other documents:
- Locations and times of events
- Actions taken by parties
- Statements attributed to individuals
- Physical evidence descriptions
- Procedural claims (what was done, when, by whom)

Also identify potential_contradictions within the same document if any facts seem inconsistent.

Output as JSON with this structure:
{
  "facts": [
    {
      "id": "F1",
      "statement": "...",
      "source": "...",
      "category": "...",
      "certainty": "...",
      "date_referenced": "...",
      "actors": [...]
    }
  ],
  "fact_count": N,
  "categories": ["list of unique categories found"],
  "potential_contradictions": [
    {"fact_ids": ["F1", "F2"], "reason": "Why these might contradict"}
  ]
}
PROMPT;

        $userContent = "DOCUMENT TEXT:\n{$text}";

        // Truncate if too long (leave room for system prompt)
        if (mb_strlen($userContent) > 150000) {
            $userContent = mb_substr($userContent, 0, 150000) . "\n\n[DOCUMENT TRUNCATED]";
        }

        $result = $this->claude->analyzeJson($systemPrompt, $userContent);

        return [
            'results' => $result['parsed'],
            'metadata' => [
                'model' => $result['model'],
                'input_tokens' => $result['usage']['input_tokens'] ?? 0,
                'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                'cost_usd' => $result['cost'],
                'processing_time_seconds' => $result['processing_time'],
                'analyzer' => 'KeyFactsAnalyzer',
                'api_calls' => 1,
            ],
        ];
    }
}
