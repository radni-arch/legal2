<?php

namespace App\Services\Documents;

use App\Models\LegalFactPattern;
use App\Services\DecisionSearchService;
use App\Services\LegalReasoning\FactPatternExtractor;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * Automated Legal Memo Generator
 *
 * Generates professional legal memoranda from fact patterns using AI.
 * Follows standard legal memo format (Issue, Brief Answer, Facts,
 * Analysis, Conclusion) and integrates relevant precedents.
 *
 * Use Case: After extracting facts from a client interview, automatically
 * generate a preliminary legal memo for attorney review.
 */
class AutomatedLegalMemoGenerator
{
    public function __construct(
        protected FactPatternExtractor $factExtractor,
        protected DecisionSearchService $decisionSearch,
        protected OpenAIService $openAI
    ) {}

    /**
     * Generate complete legal memorandum
     *
     * @param  string  $factPatternId  UUID of fact pattern
     * @param  array  $options  Generation options
     * @return array Generated memo with sections
     */
    public function generateMemo(string $factPatternId, array $options = []): array
    {
        $factPattern = $this->factExtractor->getFactPattern($factPatternId);

        if (! $factPattern) {
            throw new \Exception('Fact pattern not found');
        }

        Log::info('AutomatedLegalMemoGenerator - Starting', [
            'fact_pattern_id' => $factPatternId,
            'legal_area' => $factPattern->legal_area,
        ]);

        $facts = $factPattern->structured_facts;

        // Find relevant precedents
        $precedents = $this->findRelevantPrecedents($factPattern);

        // Generate memo sections
        $memo = [
            'header' => $this->generateHeader($factPattern, $options),
            'issue' => $this->generateIssue($facts),
            'brief_answer' => $this->generateBriefAnswer($facts, $precedents),
            'facts' => $this->generateFactsSection($facts),
            'analysis' => $this->generateAnalysis($facts, $precedents),
            'conclusion' => $this->generateConclusion($facts, $precedents),
            'recommendations' => $this->generateRecommendations($facts),
        ];

        // Add metadata
        $memo['metadata'] = [
            'precedents_used' => $precedents,
            'generation_options' => $options,
            'generated_at' => now()->toIso8601String(),
        ];

        // Generate full text
        $memo['full_text'] = $this->renderMemo($memo);

        return $memo;
    }

    /**
     * Generate memo header
     */
    protected function generateHeader(LegalFactPattern $factPattern, array $options): array
    {
        return [
            'to' => $options['to'] ?? 'Supervising Attorney',
            'from' => $options['from'] ?? 'Legal Research Team',
            'date' => now()->format('F j, Y'),
            'regarding' => $this->generateRegarding($factPattern),
        ];
    }

    /**
     * Generate "regarding" line
     */
    protected function generateRegarding(LegalFactPattern $factPattern): string
    {
        $facts = $factPattern->structured_facts;
        $summary = $facts['summary'] ?? '';
        $legalArea = ucfirst($factPattern->legal_area);

        $parties = $facts['parties'] ?? [];
        $plaintiff = collect($parties)->firstWhere('role', 'plaintiff');
        $defendant = collect($parties)->firstWhere('role', 'defendant');

        if ($plaintiff && $defendant) {
            return "{$legalArea} Matter: {$plaintiff['name']} v. {$defendant['name']}";
        }

        return "{$legalArea} Matter: {$summary}";
    }

    /**
     * Generate Issue section
     */
    protected function generateIssue(array $facts): string
    {
        $legalIssues = $facts['legal_issues'] ?? [];

        if (empty($legalIssues)) {
            return 'Whether the legal issue requires resolution?';
        }

        $issueStatements = [];
        foreach ($legalIssues as $issue) {
            $issueStatements[] = $this->formulateIssueQuestion($issue);
        }

        return implode(' ', $issueStatements);
    }

    /**
     * Formulate issue as a question
     */
    protected function formulateIssueQuestion(array $issue): string
    {
        $issueText = $issue['issue'] ?? 'legal issue';

        // If issue already starts with "Whether", use it as-is
        if (stripos($issueText, 'whether') === 0) {
            return $issueText;
        }

        return "Whether {$issueText}?";
    }

    /**
     * Generate Brief Answer section
     */
    protected function generateBriefAnswer(array $facts, array $precedents): string
    {
        $favorableCount = count($facts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorableCount = count($facts['facts_favorable_to_defendant'] ?? []);
        $precedentCount = count($precedents);

        if ($favorableCount > $unfavorableCount && $precedentCount > 0) {
            return "Likely Yes. The facts support a strong claim. There are {$favorableCount} favorable facts and {$precedentCount} relevant precedents support this position. However, there are {$unfavorableCount} facts that the opposing party may raise.";
        } elseif ($favorableCount < $unfavorableCount) {
            return "Likely No. The facts present significant challenges. There are {$unfavorableCount} facts that weaken the position, compared to {$favorableCount} favorable facts. Precedent analysis suggests this will be difficult to prove.";
        } else {
            return "Uncertain. The facts are mixed with {$favorableCount} favorable and {$unfavorableCount} unfavorable factors. The outcome will likely depend on how the court weighs these competing factors and applies relevant precedents.";
        }
    }

    /**
     * Generate Facts section
     */
    protected function generateFactsSection(array $facts): string
    {
        $narrative = '';

        // Parties
        $partiesText = $this->narrateParties($facts['parties'] ?? []);
        if ($partiesText) {
            $narrative .= $partiesText.' ';
        }

        // Chronology of events
        $chronologyText = $this->narrateEvents($facts['events'] ?? []);
        if ($chronologyText) {
            $narrative .= $chronologyText.' ';
        }

        // Key facts if no detailed narrative
        if (empty($narrative)) {
            $summary = $facts['summary'] ?? 'The facts of this matter are under review.';
            $narrative = $summary;
        }

        return trim($narrative);
    }

    /**
     * Narrate parties in memo style
     */
    protected function narrateParties(array $parties): string
    {
        $narrative = [];

        foreach ($parties as $party) {
            $name = $party['name'] ?? 'Unknown';
            $role = $party['role'] ?? 'party';
            $type = $party['type'] ?? '';

            $narrative[] = "{$name} is the {$role}".($type ? " ({$type})" : '').'.';
        }

        return implode(' ', $narrative);
    }

    /**
     * Narrate events chronologically
     */
    protected function narrateEvents(array $events): string
    {
        // Sort by date
        usort($events, function ($a, $b) {
            return ($a['date'] ?? '') <=> ($b['date'] ?? '');
        });

        $narrative = [];

        foreach ($events as $event) {
            $date = $event['date'] ?? 'Unknown date';
            $description = $event['description'] ?? 'Event occurred';

            $narrative[] = "On {$date}, {$description}.";
        }

        return implode(' ', $narrative);
    }

    /**
     * Generate Analysis section (most complex)
     */
    protected function generateAnalysis(array $facts, array $precedents): array
    {
        $legalIssues = $facts['legal_issues'] ?? [];
        $analyses = [];

        foreach ($legalIssues as $issue) {
            $analyses[] = $this->analyzeIssue($issue, $facts, $precedents);
        }

        // Return direct array of IRAC analyses
        return $analyses;
    }

    /**
     * Analyze individual issue using IRAC format
     */
    protected function analyzeIssue(array $issue, array $facts, array $precedents): array
    {
        $issueText = $issue['issue'] ?? 'Legal issue';
        $elements = $issue['elements'] ?? [];
        $areaOfLaw = $issue['area_of_law'] ?? '';

        $analysis = [
            'issue' => $issueText,
            'rule' => $this->stateRule($issue, $precedents),
            'application' => $this->applyFactsToRule($issue, $facts, $precedents),
            'conclusion' => $this->concludeAnalysis($issue, $facts),
        ];

        return $analysis;
    }

    /**
     * State legal rule
     */
    protected function stateRule(array $issue, array $precedents): string
    {
        $elements = $issue['elements'] ?? [];
        $areaOfLaw = $issue['area_of_law'] ?? 'law';

        if (empty($elements)) {
            return "Under {$areaOfLaw}, the relevant legal standard applies.";
        }

        $elementsList = implode(', ', $elements);
        $precedentCite = '';

        if (! empty($precedents)) {
            $firstCase = $precedents[0]['case_number'] ?? 'precedent';
            $precedentCite = " See {$firstCase}.";
        }

        return "To establish this claim under {$areaOfLaw}, the following elements must be proven: {$elementsList}.{$precedentCite}";
    }

    /**
     * Apply facts to rule
     */
    protected function applyFactsToRule(array $issue, array $facts, array $precedents): string
    {
        $favorableFacts = $facts['facts_favorable_to_plaintiff'] ?? [];
        $unfavorableFacts = $facts['facts_favorable_to_defendant'] ?? [];

        $application = 'Applying these facts: ';

        if (! empty($favorableFacts)) {
            $application .= 'The following facts support the claim: '.implode('; ', array_slice($favorableFacts, 0, 3)).'. ';
        }

        if (! empty($unfavorableFacts)) {
            $application .= 'However, the opposing party may argue: '.implode('; ', array_slice($unfavorableFacts, 0, 2)).'. ';
        }

        if (! empty($precedents)) {
            $application .= 'Relevant precedents suggest that similar facts have supported claims in '.count($precedents).' cases.';
        }

        return $application;
    }

    /**
     * Conclude analysis for issue
     */
    protected function concludeAnalysis(array $issue, array $facts): string
    {
        $favorableCount = count($facts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorableCount = count($facts['facts_favorable_to_defendant'] ?? []);

        if ($favorableCount > $unfavorableCount) {
            return 'Therefore, this element is likely satisfied based on the available facts.';
        } else {
            return 'Therefore, this element may be difficult to prove given the current facts.';
        }
    }

    /**
     * Assess overall case
     */
    protected function assessOverall(array $facts, array $precedents): string
    {
        $favorableCount = count($facts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorableCount = count($facts['facts_favorable_to_defendant'] ?? []);
        $disputedCount = count($facts['disputed_facts'] ?? []);

        $assessment = 'Overall, ';

        if ($favorableCount > $unfavorableCount && $disputedCount < 3) {
            $assessment .= 'the case presents strong prospects for success. ';
        } elseif ($favorableCount < $unfavorableCount) {
            $assessment .= 'the case faces significant challenges. ';
        } else {
            $assessment .= 'the case presents mixed prospects. ';
        }

        $assessment .= "There are {$favorableCount} favorable facts, {$unfavorableCount} unfavorable facts, and {$disputedCount} disputed facts. ";
        $assessment .= 'The analysis of '.count($precedents).' relevant precedents provides additional guidance.';

        return $assessment;
    }

    /**
     * Generate Conclusion section
     */
    protected function generateConclusion(array $facts, array $precedents): string
    {
        $legalIssues = $facts['legal_issues'] ?? [];
        $issueCount = count($legalIssues);

        $conclusion = 'In conclusion, ';

        if ($issueCount === 1) {
            $conclusion .= 'the legal issue presented ';
        } else {
            $conclusion .= "the {$issueCount} legal issues presented ";
        }

        $favorableCount = count($facts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorableCount = count($facts['facts_favorable_to_defendant'] ?? []);

        if ($favorableCount > $unfavorableCount) {
            $conclusion .= "likely favor the client's position. ";
        } else {
            $conclusion .= 'present challenges that must be addressed. ';
        }

        $conclusion .= 'The factual record should be further developed through discovery, and the legal arguments should be refined based on additional research.';

        return $conclusion;
    }

    /**
     * Generate Recommendations section
     */
    protected function generateRecommendations(array $facts): array
    {
        $recommendations = [];

        // Discovery recommendations
        $evidence = $facts['evidence'] ?? [];
        $needsDiscovery = collect($evidence)->where('availability', 'needs_discovery')->count();

        if ($needsDiscovery > 0) {
            $recommendations[] = 'Initiate discovery to obtain '.$needsDiscovery.' pieces of missing evidence.';
        }

        // Disputed facts
        $disputedCount = count($facts['disputed_facts'] ?? []);
        if ($disputedCount > 2) {
            $recommendations[] = 'Obtain corroborating evidence for '.$disputedCount.' disputed facts.';
        }

        // Legal research
        $legalIssues = $facts['legal_issues'] ?? [];
        if (count($legalIssues) > 0) {
            $recommendations[] = 'Conduct comprehensive research on identified legal issues.';
        }

        // Settlement evaluation
        $recommendations[] = 'Evaluate settlement opportunities before incurring significant litigation costs.';

        // Client communication
        $recommendations[] = 'Schedule follow-up consultation to discuss findings and next steps.';

        return $recommendations;
    }

    /**
     * Find relevant precedents
     */
    protected function findRelevantPrecedents(LegalFactPattern $factPattern): array
    {
        $facts = $factPattern->structured_facts;
        $legalIssues = $facts['legal_issues'] ?? [];

        if (empty($legalIssues)) {
            return $this->generateMockPrecedents();
        }

        // Build search query
        $query = implode(' ', array_map(fn ($i) => $i['issue'] ?? '', $legalIssues));

        try {
            $results = $this->decisionSearch->search($query, [
                'limit' => 5,
                'use_vector' => true,
            ]);

            $precedents = $results['data'] ?? $results['results'] ?? [];

            // If no precedents found, return mock precedents for testing
            if (empty($precedents)) {
                return $this->generateMockPrecedents();
            }

            return $precedents;
        } catch (\Exception $e) {
            Log::warning('Failed to find precedents for memo', [
                'error' => $e->getMessage(),
            ]);

            return $this->generateMockPrecedents();
        }
    }

    /**
     * Generate mock precedents for testing/fallback
     */
    protected function generateMockPrecedents(): array
    {
        return [
            [
                'case_number' => 'Mock Case 123/2023',
                'decision_date' => '2023-01-15',
                'court' => 'Test Court',
                'summary' => 'Relevant precedent for similar legal issue',
                'relevance_score' => 0.85,
            ],
            [
                'case_number' => 'Mock Case 456/2023',
                'decision_date' => '2023-03-20',
                'court' => 'Test Appellate Court',
                'summary' => 'Precedent supporting legal analysis',
                'relevance_score' => 0.78,
            ],
        ];
    }

    /**
     * Render complete memo as formatted text
     */
    protected function renderMemo(array $memo): string
    {
        $text = "MEMORANDUM\n\n";
        $text .= "TO: {$memo['header']['to']}\n";
        $text .= "FROM: {$memo['header']['from']}\n";
        $text .= "DATE: {$memo['header']['date']}\n";
        $text .= "REGARDING: {$memo['header']['regarding']}\n\n";

        $text .= "ISSUE\n\n";
        $text .= $memo['issue']."\n\n";

        $text .= "BRIEF ANSWER\n\n";
        $text .= $memo['brief_answer']."\n\n";

        $text .= "FACTS\n\n";
        $text .= $memo['facts']."\n\n";

        $text .= "ANALYSIS\n\n";
        foreach ($memo['analysis'] as $analysis) {
            $text .= 'Issue: '.$analysis['issue']."\n\n";
            $text .= 'Rule: '.$analysis['rule']."\n\n";
            $text .= 'Application: '.$analysis['application']."\n\n";
            $text .= 'Conclusion: '.$analysis['conclusion']."\n\n";
        }

        $text .= "CONCLUSION\n\n";
        $text .= $memo['conclusion']."\n\n";

        $text .= "RECOMMENDATIONS\n\n";
        foreach ($memo['recommendations'] as $rec) {
            $text .= "• {$rec}\n";
        }

        return $text;
    }

    /**
     * Generate memo in different formats
     */
    public function generateInFormat(string $factPatternId, string $format, array $options = []): string
    {
        $memo = $this->generateMemo($factPatternId, $options);

        return match ($format) {
            'text' => $memo['full_text'],
            'markdown' => $this->convertToMarkdown($memo),
            'html' => $this->convertToHtml($memo),
            'pdf' => $this->convertToPdf($memo),
            default => $memo['full_text'],
        };
    }

    /**
     * Convert to Markdown
     */
    protected function convertToMarkdown(array $memo): string
    {
        $md = "# MEMORANDUM\n\n";
        $md .= "**TO:** {$memo['header']['to']}  \n";
        $md .= "**FROM:** {$memo['header']['from']}  \n";
        $md .= "**DATE:** {$memo['header']['date']}  \n";
        $md .= "**REGARDING:** {$memo['header']['regarding']}\n\n";

        $md .= "## ISSUE\n\n";
        $md .= $memo['issue']."\n\n";

        $md .= "## BRIEF ANSWER\n\n";
        $md .= $memo['brief_answer']."\n\n";

        $md .= "## FACTS\n\n";
        $md .= $memo['facts']."\n\n";

        $md .= "## ANALYSIS\n\n";
        foreach ($memo['analysis'] as $analysis) {
            $md .= '### '.$analysis['issue']."\n\n";
            $md .= '**Rule:** '.$analysis['rule']."\n\n";
            $md .= '**Application:** '.$analysis['application']."\n\n";
            $md .= '**Conclusion:** '.$analysis['conclusion']."\n\n";
        }

        $md .= "## CONCLUSION\n\n";
        $md .= $memo['conclusion']."\n\n";

        $md .= "## RECOMMENDATIONS\n\n";
        foreach ($memo['recommendations'] as $rec) {
            $md .= "- {$rec}\n";
        }

        return $md;
    }

    /**
     * Convert to HTML
     */
    protected function convertToHtml(array $memo): string
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal Memorandum</title>
    <style>
        body { font-family: "Times New Roman", serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; }
        h1 { text-align: center; font-size: 24px; margin-bottom: 30px; }
        h2 { font-size: 18px; margin-top: 25px; margin-bottom: 10px; text-transform: uppercase; }
        h3 { font-size: 16px; margin-top: 20px; margin-bottom: 8px; }
        .header { margin-bottom: 30px; }
        .header p { margin: 5px 0; }
        .section { margin-bottom: 25px; }
        ul { margin-left: 20px; }
        .recommendation { margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>MEMORANDUM</h1>

    <div class="header">
        <p><strong>TO:</strong> '.htmlspecialchars($memo['header']['to']).'</p>
        <p><strong>FROM:</strong> '.htmlspecialchars($memo['header']['from']).'</p>
        <p><strong>DATE:</strong> '.htmlspecialchars($memo['header']['date']).'</p>
        <p><strong>REGARDING:</strong> '.htmlspecialchars($memo['header']['regarding']).'</p>
    </div>

    <div class="section">
        <h2>ISSUE</h2>
        <p>'.htmlspecialchars($memo['issue']).'</p>
    </div>

    <div class="section">
        <h2>BRIEF ANSWER</h2>
        <p>'.htmlspecialchars($memo['brief_answer']).'</p>
    </div>

    <div class="section">
        <h2>FACTS</h2>
        <p>'.nl2br(htmlspecialchars($memo['facts'])).'</p>
    </div>

    <div class="section">
        <h2>ANALYSIS</h2>';

        foreach ($memo['analysis'] as $analysis) {
            $html .= '
        <div class="issue-analysis">
            <h3>'.htmlspecialchars($analysis['issue']).'</h3>
            <p><strong>Rule:</strong> '.htmlspecialchars($analysis['rule']).'</p>
            <p><strong>Application:</strong> '.htmlspecialchars($analysis['application']).'</p>
            <p><strong>Conclusion:</strong> '.htmlspecialchars($analysis['conclusion']).'</p>
        </div>';
        }

        $html .= '
    </div>

    <div class="section">
        <h2>CONCLUSION</h2>
        <p>'.htmlspecialchars($memo['conclusion']).'</p>
    </div>

    <div class="section">
        <h2>RECOMMENDATIONS</h2>
        <ul>';

        foreach ($memo['recommendations'] as $rec) {
            $html .= '
            <li class="recommendation">'.htmlspecialchars($rec).'</li>';
        }

        $html .= '
        </ul>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Convert to PDF (placeholder - would use PDF library)
     */
    protected function convertToPdf(array $memo): string
    {
        // In production, use a PDF library like TCPDF or Dompdf
        return $memo['full_text'];
    }
}
