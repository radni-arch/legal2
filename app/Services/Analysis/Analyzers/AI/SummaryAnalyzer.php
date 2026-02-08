<?php

namespace App\Services\Analysis\Analyzers\AI;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class SummaryAnalyzer implements DocumentAnalyzerInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function type(): string
    {
        return DocumentAnalysis::TYPE_SUMMARY;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_AI_BASIC;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $systemPrompt = <<<PROMPT
You are a Croatian legal document analyst. Your task is to generate a structured summary of the provided legal document.

Extract and organize the following information:

1. **parties**: Identify all parties involved
   - plaintiff/tuzitelj: The party bringing the case
   - defendant/optuzenik: The party defending
   - witnesses/svjedoci: Key witnesses mentioned
   - legal_representatives: Lawyers, attorneys mentioned

2. **subject_matter**: What is the case about? (criminal charge, civil dispute, administrative matter)

3. **procedural_posture**: Current stage of proceedings
   - Is this a first instance (prvostupanjska), appeal (zalba), or other proceeding?
   - What court is handling the case?

4. **key_arguments**: Main arguments from each party
   - prosecution/tuziteljstvo arguments
   - defense/obrana arguments
   - key evidence cited

5. **ruling**: If present, the court's decision
   - outcome: guilty/not_guilty/dismissed/remanded/other
   - sentence: Any penalties imposed
   - reasoning: Brief summary of court's reasoning

6. **one_paragraph_summary**: A concise summary of the entire document (in Croatian)

Output as JSON with this structure:
{
  "parties": {
    "plaintiff": "...",
    "defendant": "...",
    "witnesses": [...],
    "legal_representatives": [...]
  },
  "subject_matter": "...",
  "procedural_posture": "...",
  "key_arguments": [
    {"party": "prosecution|defense", "argument": "..."}
  ],
  "ruling": {
    "outcome": "...",
    "sentence": "...",
    "reasoning": "..."
  },
  "one_paragraph_summary": "..."
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
                'analyzer' => 'SummaryAnalyzer',
                'api_calls' => 1,
            ],
        ];
    }
}
