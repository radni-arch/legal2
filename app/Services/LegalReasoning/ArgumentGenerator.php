<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\AnalysisException;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ArgumentGenerator
{
    public function __construct(
        protected OpenAIService $openAI,
        protected CitationAnalyzer $citationAnalyzer
    ) {}

    /**
     * Generate legal arguments for a case using IRAC method
     */
    public function generateArguments(string $caseId, array $objectives = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ArgumentGenerator: generateArguments initiated', [
            'case_id' => $caseId,
            'objectives_count' => count($objectives),
            'user_id' => auth()->id(),
        ]);

        try {
            $case = LegalCase::with('documents')->findOrFail($caseId);

            // Extract legal issues from case
            $legalIssues = $this->extractLegalIssues($case, $objectives);

            $arguments = [];

            foreach ($legalIssues as $issue) {
                // Find supporting precedents
                $precedents = $this->findSupportingPrecedents($issue);

                // Find applicable law
                $applicableLaw = $this->findApplicableLaw($issue);

                // Apply law to facts
                $application = $this->applyLawToFacts($issue, $case, $applicableLaw);

                // Build IRAC argument structure
                $argument = $this->buildArgument([
                    'issue' => $issue['issue'],
                    'claim' => $issue['claim'],
                    'rule' => $applicableLaw,
                    'application' => $application,
                    'conclusion' => $issue['desired_outcome'],
                ]);

                // Enhance with LLM
                $enhancedArgument = $this->enhanceArgument($argument, $precedents, $case);

                // Find counter-arguments
                $counterarguments = $this->findCounterArguments($issue, $case);

                // Score argument strength
                $strength = $this->scoreArgumentStrength($argument, $precedents, $applicableLaw);

                // Build rebuttal strategy
                $rebuttal = $this->buildRebuttal($counterarguments, $precedents);

                $arguments[] = [
                    'issue' => $issue['issue'],
                    'claim' => $issue['claim'],
                    'argument' => $enhancedArgument,
                    'irac_structure' => $argument,
                    'strength_score' => round($strength, 3),
                    'supporting_precedents' => array_slice($precedents, 0, 5),
                    'applicable_law' => $applicableLaw,
                    'potential_counterarguments' => $counterarguments,
                    'rebuttal_strategy' => $rebuttal,
                ];
            }

            // Rank arguments by strength
            usort($arguments, fn ($a, $b) => $b['strength_score'] <=> $a['strength_score']);

            $result = [
                'case_id' => $caseId,
                'arguments' => $arguments,
                'total_arguments' => count($arguments),
                'strongest_argument' => $arguments[0] ?? null,
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ArgumentGenerator: generateArguments completed', [
                'case_id' => $caseId,
                'arguments_generated' => count($arguments),
                'strongest_score' => $arguments[0]['strength_score'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('ArgumentGenerator: generateArguments failed with AnalysisException', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('ArgumentGenerator: Case not found', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                "Case not found: {$caseId}",
                AnalysisException::RESOURCE_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('ArgumentGenerator: generateArguments failed with unexpected exception', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Argument generation failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Extract legal issues from case
     *
     * @throws \Throwable
     */
    protected function extractLegalIssues(LegalCase $case, array $objectives): array
    {
        // Prepare case context for LLM
        $caseContext = $this->prepareCaseContext($case);

        $objectivesText = ! empty($objectives)
            ? 'Client objectives: '.implode('; ', $objectives)
            : 'Identify all key legal issues in this case.';

        $prompt = "You are a legal expert. Analyze this case and extract the key legal issues.

Case Context:
{$caseContext}

{$objectivesText}

For each legal issue, provide:
1. The specific legal issue/question
2. The claim we want to make
3. The desired outcome

Return as JSON array:
[
  {
    \"issue\": \"Precise legal issue or question\",
    \"claim\": \"Our position/claim\",
    \"desired_outcome\": \"What we want the court to decide\",
    \"priority\": \"high|medium|low\"
  }
]";

        // Use chat with proper messages structure
        $response = $this->openAI->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'max_tokens' => 1500,
            'temperature' => 0.3,
        ]);

        // Extract JSON from response
        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $issues = json_decode($matches[0], true);

            return $issues ?? [];
        }

        Log::warning('ArgumentGenerator - Failed to extract issues, using default');

        return [[
            'issue' => 'General legal merit of the case',
            'claim' => 'Client has a valid legal claim',
            'desired_outcome' => 'Favorable ruling for client',
            'priority' => 'high',
        ]];
    }

    /**
     * Find supporting precedents for an issue
     */
    protected function findSupportingPrecedents(array $issue, int $limit = 10): array
    {
        // Search for relevant court decisions using keyword matching
        $keywords = $this->extractKeywords($issue['issue'].' '.$issue['claim']);

        $precedents = CourtDecision::query()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'ILIKE', "%{$keyword}%")
                        ->orWhere('description', 'ILIKE', "%{$keyword}%");
                }
            })
            ->orderByDesc('decision_date')
            ->limit($limit)
            ->get();

        return $precedents->map(function ($decision) {
            $analysis = $this->citationAnalyzer->analyzeAuthority($decision->id);

            return [
                'decision_id' => $decision->id,
                'case_title' => $decision->title,
                'court' => $decision->court,
                'decision_date' => $decision->decision_date,
                'ecli' => $decision->ecli,
                'authority_score' => $analysis['authority_score'] ?? 0,
                'summary' => $decision->description,
                'key_holding' => $this->extractKeyHolding($decision),
            ];
        })->toArray();
    }

    /**
     * Find applicable law for an issue
     */
    protected function findApplicableLaw(array $issue): array
    {
        $keywords = $this->extractKeywords($issue['issue']);

        $laws = Law::query()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'ILIKE', "%{$keyword}%")
                        ->orWhere('content', 'ILIKE', "%{$keyword}%");
                }
            })
            ->whereNull('repeal_date') // Only active laws (not repealed)
            ->orderByDesc('effective_date')
            ->limit(5)
            ->get();

        return $laws->map(function ($law) {
            return [
                'law_id' => $law->id,
                'title' => $law->title,
                'article' => $law->law_number ?? 'N/A',
                'section' => $law->section ?? $law->chapter ?? '',
                'text' => substr($law->content ?? '', 0, 500), // First 500 chars
                'jurisdiction' => $law->jurisdiction,
                'effective_date' => $law->effective_date,
            ];
        })->toArray();
    }

    /**
     * Apply law to case facts
     */
    protected function applyLawToFacts(array $issue, LegalCase $case, array $applicableLaw): string
    {
        $caseContext = $this->prepareCaseContext($case);
        $lawsText = collect($applicableLaw)
            ->map(fn ($law) => "{$law['title']}: {$law['text']}")
            ->implode("\n\n");

        $prompt = "Apply the following law to the case facts:

Legal Issue: {$issue['issue']}

Applicable Law:
{$lawsText}

Case Facts:
{$caseContext}

Explain how the law applies to these specific facts. Be precise and cite specific provisions.";

        $response = $this->openAI->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'max_tokens' => 800,
            'temperature' => 0.3,
        ]);

        return $response['choices'][0]['message']['content'] ?? 'Application of law to facts pending analysis.';
    }

    /**
     * Build IRAC argument structure
     */
    protected function buildArgument(array $components): array
    {
        return [
            'issue' => $components['issue'],
            'rule' => $components['rule'],
            'application' => $components['application'],
            'conclusion' => $components['conclusion'],
            'structure' => 'IRAC',
        ];
    }

    /**
     * Enhance argument using LLM
     */
    protected function enhanceArgument(array $argument, array $precedents, LegalCase $case): string
    {
        $precedentsText = collect($precedents)
            ->take(5)
            ->map(fn ($p) => "- {$p['case_title']} ({$p['court']}, {$p['decision_date']}): {$p['key_holding']}")
            ->implode("\n");

        $lawsText = collect($argument['rule'])
            ->map(fn ($law) => "- {$law['title']} ({$law['article']}): {$law['text']}")
            ->implode("\n");

        $prompt = "You are an expert legal advocate. Write a compelling legal argument using the IRAC method.

**ISSUE:**
{$argument['issue']}

**RULE (Applicable Law):**
{$lawsText}

**APPLICATION:**
{$argument['application']}

**CONCLUSION:**
{$argument['conclusion']}

**SUPPORTING PRECEDENTS:**
{$precedentsText}

Write a persuasive, well-structured legal argument (300-500 words) that:
1. Clearly states the legal issue
2. Cites the applicable law
3. Applies the law to the facts with precision
4. References supporting precedents
5. Reaches a compelling conclusion
6. Uses professional legal language
7. Anticipates and addresses potential weaknesses

Make it courtroom-ready and persuasive.";

        $response = $this->openAI->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'max_tokens' => 1200,
            'temperature' => 0.4,
        ]);

        return $response['choices'][0]['message']['content'] ?? 'Enhanced argument generation pending.';
    }

    /**
     * Find potential counter-arguments
     */
    protected function findCounterArguments(array $issue, LegalCase $case): array
    {
        $caseContext = $this->prepareCaseContext($case);

        $prompt = "You are representing the opposing side. Identify potential counter-arguments to this claim:

Claim: {$issue['claim']}
Issue: {$issue['issue']}

Case Context:
{$caseContext}

List 3-5 strongest counter-arguments the opposition might raise. For each, provide:
1. The counter-argument
2. The legal basis
3. Strength (high/medium/low)

Return as JSON:
[
  {
    \"argument\": \"Counter-argument text\",
    \"legal_basis\": \"Legal foundation\",
    \"strength\": \"high|medium|low\"
  }
]";

        $response = $this->openAI->chat([
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'max_tokens' => 1000,
            'temperature' => 0.4,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $counterArgs = json_decode($matches[0], true);

            return $counterArgs ?? [];
        }

        return [];
    }

    /**
     * Score argument strength
     */
    protected function scoreArgumentStrength(array $argument, array $precedents, array $applicableLaw): float
    {
        $score = 0.0;

        // Factor 1: Number and quality of precedents (max 0.35)
        $precedentScore = 0.0;
        foreach (array_slice($precedents, 0, 5) as $precedent) {
            $authorityScore = $precedent['authority_score'] ?? 0;
            $precedentScore += ($authorityScore * 0.07); // Max 0.35 for 5 precedents
        }
        $score += min($precedentScore, 0.35);

        // Factor 2: Applicable law clarity (max 0.25)
        $lawScore = min(count($applicableLaw) * 0.08, 0.25);
        $score += $lawScore;

        // Factor 3: Application strength (based on length, max 0.2)
        $applicationLength = strlen($argument['application']);
        $applicationScore = min($applicationLength / 2000, 0.2);
        $score += $applicationScore;

        // Factor 4: Recency of precedents (max 0.1)
        $avgAge = $this->calculateAveragePrecedentAge($precedents);
        $recencyScore = $avgAge < 5 ? 0.1 : ($avgAge < 10 ? 0.07 : 0.05);
        $score += $recencyScore;

        // Factor 5: Rule clarity (max 0.1)
        $ruleScore = ! empty($applicableLaw) && count($applicableLaw) > 0 ? 0.1 : 0.05;
        $score += $ruleScore;

        return min($score, 1.0);
    }

    /**
     * Build rebuttal strategy
     */
    protected function buildRebuttal(array $counterarguments, array $precedents): array
    {
        if (empty($counterarguments)) {
            return [];
        }

        $rebuttals = [];

        foreach ($counterarguments as $counter) {
            // Handle both string and array formats
            $counterArg = is_array($counter) ? ($counter['argument'] ?? $counter['claim'] ?? '') : $counter;
            $counterBasis = is_array($counter) ? ($counter['legal_basis'] ?? '') : '';
            $counterStrength = is_array($counter) ? ($counter['strength'] ?? 0.5) : 0.5;

            if (empty($counterArg)) {
                continue;
            }

            $precedentsText = collect($precedents)
                ->take(3)
                ->map(fn ($p) => "{$p['case_title']}: {$p['key_holding']}")
                ->implode("\n");

            $prompt = "Counter-argument: {$counterArg}

Based on: {$counterBasis}

Using these precedents:
{$precedentsText}

Write a brief but effective rebuttal (2-3 sentences) that:
1. Acknowledges the counter-argument
2. Distinguishes or undermines it
3. Reinforces our position";

            $response = $this->openAI->chat([
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            $rebuttals[] = [
                'counter_argument' => $counterArg,
                'counter_strength' => $counterStrength,
                'rebuttal' => $response['choices'][0]['message']['content'] ?? 'Rebuttal pending.',
            ];
        }

        return $rebuttals;
    }

    /**
     * Prepare case context for prompts
     */
    protected function prepareCaseContext(LegalCase $case): string
    {
        return sprintf(
            "Case: %s\nCourt: %s\nJurisdiction: %s\nParties: %s vs %s\nDescription: %s",
            $case->case_number ?? 'N/A',
            $case->court ?? 'N/A',
            $case->jurisdiction ?? 'N/A',
            $case->client_name ?? 'Client',
            $case->opponent_name ?? 'Opponent',
            substr($case->description ?? '', 0, 500)
        );
    }

    /**
     * Extract keywords from text
     */
    protected function extractKeywords(string $text): array
    {
        // Simple keyword extraction (remove common words)
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'was', 'are', 'were'];

        $words = preg_split('/\s+/', strtolower($text));
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords);
        });

        return array_unique(array_values($keywords));
    }

    /**
     * Extract key holding from decision
     */
    protected function extractKeyHolding(CourtDecision $decision): string
    {
        // Extract first 200 characters of description as key holding
        return substr($decision->description ?? 'No summary available', 0, 200);
    }

    /**
     * Calculate average age of precedents
     */
    protected function calculateAveragePrecedentAge(array $precedents): float
    {
        if (empty($precedents)) {
            return 999;
        }

        $ages = [];
        foreach ($precedents as $precedent) {
            if (! empty($precedent['decision_date'])) {
                $date = \Carbon\Carbon::parse($precedent['decision_date']);
                $ages[] = now()->diffInYears($date);
            }
        }

        return count($ages) > 0 ? array_sum($ages) / count($ages) : 999;
    }
}
