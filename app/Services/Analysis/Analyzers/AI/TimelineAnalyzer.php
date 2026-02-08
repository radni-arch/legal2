<?php

namespace App\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class TimelineAnalyzer implements DocumentAnalyzerInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function type(): string
    {
        return DocumentAnalysis::TYPE_TIMELINE;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_AI_BASIC;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        // Get previously extracted dates and entities to feed as context
        $dateAnalysis = $document->latestAnalysis(DocumentAnalysis::TYPE_DATES);
        $entityAnalysis = $document->latestAnalysis(DocumentAnalysis::TYPE_ENTITIES);

        $context = "PREVIOUSLY EXTRACTED DATA:\n";
        if ($dateAnalysis) {
            $context .= "Dates found: " . json_encode($dateAnalysis->results['dates'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
        if ($entityAnalysis) {
            $context .= "Entities: " . json_encode($entityAnalysis->results['entities'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }

        $systemPrompt = <<<PROMPT
You are a Croatian legal document analyst. Your task is to extract a chronological timeline of events from the provided legal document.

For each event, provide:
- date: ISO date (YYYY-MM-DD) or approximate ("2024-01" for month-only, "2024" for year-only)
- description: Clear description of what happened (in Croatian)
- actors: Who was involved
- significance: "high", "medium", or "low" based on legal relevance
- source_quote: Brief quote from the document supporting this event (max 50 words)
- document_section: Where in the document this was found (e.g., "page 3", "paragraph 12")

Output as JSON with this structure:
{
  "events": [...],
  "narrative_summary": "Brief narrative summary of the case chronology (in Croatian)",
  "key_periods": [{"label": "...", "start": "...", "end": "...", "description": "..."}],
  "gaps": ["Periods where events are missing or unclear"]
}
PROMPT;

        $userContent = "{$context}\n\nDOCUMENT TEXT:\n{$text}";

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
                'analyzer' => 'TimelineAnalyzer',
                'api_calls' => 1,
            ],
        ];
    }
}
