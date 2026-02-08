<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryPackage;
use App\Models\LegalFactPattern;
use App\Services\LegalReasoning\FactPatternExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Discovery Request Generator
 *
 * Automatically generates discovery requests (interrogatories, document
 * requests, requests for admission) based on fact patterns. Identifies
 * gaps in evidence and generates targeted discovery to fill them.
 *
 * Use Case: After extracting facts, automatically generate a complete
 * set of discovery requests tailored to the specific case.
 */
class DiscoveryRequestGenerator
{
    public function __construct(
        protected FactPatternExtractor $factExtractor
    ) {}

    /**
     * Generate complete discovery package
     *
     * @param  string  $factPatternId  UUID of fact pattern
     * @return DiscoveryPackage Discovery package model
     */
    public function generateDiscoveryPackage(string $factPatternId): DiscoveryPackage
    {
        $factPattern = LegalFactPattern::findOrFail($factPatternId);

        Log::info('DiscoveryRequestGenerator - Starting', [
            'fact_pattern_id' => $factPatternId,
        ]);

        $facts = $factPattern->structured_facts;
        $facts['legal_area'] = $factPattern->legal_area;

        $requests = [
            'interrogatories' => $this->generateInterrogatories($facts),
            'document_requests' => $this->generateDocumentRequests($facts),
            'requests_for_admission' => $this->generateRequestsForAdmission($facts),
            'deposition_notices' => $this->generateDepositionNotices($facts),
        ];

        $metadata = [
            'generated_at' => now()->toIso8601String(),
            'legal_area' => $factPattern->legal_area,
            'estimated_cost' => $this->generateCostEstimate($facts),
            'timeline' => $this->generateTimeline($facts),
        ];

        return DiscoveryPackage::create([
            'user_id' => $factPattern->user_id,
            'fact_pattern_id' => $factPatternId,
            'requests' => $requests,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate interrogatories
     */
    protected function generateInterrogatories(array $facts): array
    {
        $interrogatories = [];
        $counter = 1;

        // Standard identification interrogatories
        $interrogatories[] = [
            'number' => $counter++,
            'text' => 'State your full name, current address, and all addresses where you have resided during the past five years.',
            'category' => 'identification',
        ];

        // Interrogatories about disputed facts
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach ($disputedFacts as $fact) {
            $interrogatories[] = [
                'number' => $counter++,
                'text' => "Describe in detail all facts supporting your position regarding: {$fact}",
                'category' => 'disputed_facts',
            ];
        }

        // Interrogatories about events
        $events = $facts['events'] ?? [];
        foreach (array_slice($events, 0, 3) as $event) {
            $interrogatories[] = [
                'number' => $counter++,
                'text' => "Describe your knowledge of the following event: {$event['description']}. Include the date, time, location, persons present, and all relevant details.",
                'category' => 'events',
            ];
        }

        // Interrogatories about damages
        $damages = $facts['damages_or_relief_sought'] ?? [];
        if (! empty($damages)) {
            $interrogatories[] = [
                'number' => $counter++,
                'text' => 'Itemize all damages you claim, including the amount of each item and the factual basis for each claim.',
                'category' => 'damages',
            ];
        }

        // Interrogatories about witnesses
        $interrogatories[] = [
            'number' => $counter++,
            'text' => 'Identify all persons with knowledge of the facts of this case, including their names, addresses, and the substance of their knowledge.',
            'category' => 'witnesses',
        ];

        // Interrogatories about documents
        $interrogatories[] = [
            'number' => $counter++,
            'text' => 'Identify all documents that support your claims or defenses in this matter.',
            'category' => 'documents',
        ];

        return [
            'total' => count($interrogatories),
            'items' => $interrogatories,
        ];
    }

    /**
     * Generate document requests
     */
    protected function generateDocumentRequests(array $facts): array
    {
        $requests = [];
        $counter = 1;

        // Contract cases
        if (($facts['legal_area'] ?? '') === 'contract') {
            $requests[] = [
                'number' => $counter++,
                'description' => 'All contracts, agreements, or understandings between the parties, including drafts, amendments, and related correspondence.',
                'category' => 'contracts',
                'relevance' => 'To establish the terms and conditions of the contractual relationship between the parties.',
            ];
        }

        // Communications
        $requests[] = [
            'number' => $counter++,
            'description' => 'All communications between the parties, including emails, letters, text messages, and memoranda.',
            'category' => 'communications',
            'relevance' => 'To understand the communications and interactions between the parties relevant to the dispute.',
        ];

        // Financial documents for damage claims
        $damages = $facts['damages_or_relief_sought'] ?? [];
        if (($damages['type'] ?? '') === 'monetary') {
            $requests[] = [
                'number' => $counter++,
                'description' => 'All financial records, invoices, receipts, and accounting documents related to the claimed damages.',
                'category' => 'financial',
                'relevance' => 'To verify and substantiate the monetary damages claimed in this matter.',
            ];
        }

        // Evidence documents
        $evidence = $facts['evidence'] ?? [];
        foreach ($evidence as $item) {
            if (($item['type'] ?? '') === 'documentary') {
                $description = $item['description'] ?? 'evidence items';
                $requests[] = [
                    'number' => $counter++,
                    'description' => "All documents related to: {$description}",
                    'category' => 'evidence',
                    'relevance' => 'To obtain documentary evidence relevant to the facts and issues in dispute.',
                ];
            }
        }

        // Photos and recordings
        $requests[] = [
            'number' => $counter++,
            'description' => 'All photographs, videos, audio recordings, or other media related to this matter.',
            'category' => 'media',
            'relevance' => 'To obtain visual and audio evidence that may support or refute claims in this case.',
        ];

        // Expert reports
        $requests[] = [
            'number' => $counter++,
            'description' => 'All expert reports, opinions, or analyses relevant to this case.',
            'category' => 'experts',
            'relevance' => 'To identify expert opinions and analyses that may be relied upon by the parties.',
        ];

        return [
            'total' => count($requests),
            'items' => $requests,
        ];
    }

    /**
     * Generate requests for admission
     */
    protected function generateRequestsForAdmission(array $facts): array
    {
        $requests = [];
        $counter = 1;

        // Admissions about undisputed facts
        $undisputedFacts = $facts['undisputed_facts'] ?? [];
        foreach ($undisputedFacts as $fact) {
            $requests[] = [
                'number' => $counter++,
                'statement' => "Admit that: {$fact}",
                'purpose' => 'To narrow the issues for trial by establishing undisputed facts.',
            ];
        }

        // Admissions about authenticity
        $requests[] = [
            'number' => $counter++,
            'statement' => 'Admit that all documents produced in response to the document requests are genuine and authentic.',
            'purpose' => 'To establish the authenticity of documentary evidence and avoid the need for foundation testimony at trial.',
        ];

        // Admissions about elements
        $legalIssues = $facts['legal_issues'] ?? [];
        foreach ($legalIssues as $issue) {
            $elements = $issue['elements'] ?? [];
            foreach (array_slice($elements, 0, 2) as $element) {
                $requests[] = [
                    'number' => $counter++,
                    'statement' => "Admit that: {$element}",
                    'purpose' => 'To establish key legal elements and narrow the scope of disputed issues.',
                ];
            }
        }

        return [
            'total' => count($requests),
            'items' => $requests,
        ];
    }

    /**
     * Generate deposition notices
     */
    protected function generateDepositionNotices(array $facts): array
    {
        $notices = [];

        $parties = $facts['parties'] ?? [];
        foreach ($parties as $party) {
            if (in_array($party['role'] ?? '', ['defendant', 'witness'])) {
                $topics = $this->generateDepositionTopics($facts, $party);
                $notices[] = [
                    'deponent' => $party['name'] ?? 'Unknown',
                    'role' => $party['role'] ?? 'party',
                    'topics' => $topics,
                    'estimated_duration' => $this->estimateDepositionDuration($topics, $party),
                ];
            }
        }

        return [
            'total' => count($notices),
            'notices' => $notices,
        ];
    }

    /**
     * Generate deposition topics
     */
    protected function generateDepositionTopics(array $facts, array $party): array
    {
        $topics = [];

        // Topics about events
        $events = $facts['events'] ?? [];
        foreach (array_slice($events, 0, 3) as $event) {
            $topics[] = "Knowledge of and participation in: {$event['description']}";
        }

        // Topics about disputed facts
        $disputedFacts = $facts['disputed_facts'] ?? [];
        foreach (array_slice($disputedFacts, 0, 3) as $fact) {
            $topics[] = "Position and evidence regarding: {$fact}";
        }

        return $topics;
    }

    /**
     * Estimate deposition duration based on topics and party role
     */
    protected function estimateDepositionDuration(array $topics, array $party): string
    {
        $baseHours = 2;
        $topicCount = count($topics);

        // Add time based on number of topics (30 minutes per topic)
        $additionalHours = ($topicCount * 0.5);

        // Key parties may need more time
        if (($party['role'] ?? '') === 'defendant') {
            $additionalHours += 1;
        }

        $totalHours = $baseHours + $additionalHours;

        // Round to nearest half hour and format
        $roundedHours = round($totalHours * 2) / 2;

        return $roundedHours.' hours';
    }

    /**
     * Generate cost estimate
     */
    protected function generateCostEstimate(array $facts): array
    {
        $parties = count($facts['parties'] ?? []);
        $issues = count($facts['legal_issues'] ?? []);
        $disputedFacts = count($facts['disputed_facts'] ?? []);
        $evidence = count($facts['evidence'] ?? []);

        // Base costs
        $interrogatoryCost = 500;
        $documentProductionCost = 1000;
        $depositionCost = 2000;

        // Calculate interrogatory costs
        $interrogatoriesTotal = 5 + $disputedFacts + min(3, count($facts['events'] ?? []));
        $interrogatoriesCost = $interrogatoriesTotal * 100;

        // Calculate document production costs
        $documentRequestsTotal = 3 + count(array_filter($facts['evidence'] ?? [], fn ($e) => ($e['type'] ?? '') === 'documentary'));
        $documentCost = $documentProductionCost + ($documentRequestsTotal * 200);

        // Calculate deposition costs
        $depositionCount = count(array_filter($facts['parties'] ?? [], fn ($p) => in_array($p['role'] ?? '', ['defendant', 'witness'])));
        $depositionsTotalCost = $depositionCount * $depositionCost;

        $totalMin = $interrogatoriesCost + $documentCost + $depositionsTotalCost;
        $totalMax = $totalMin * 1.5;

        return [
            'total_min' => round($totalMin, 2),
            'total_max' => round($totalMax, 2),
            'breakdown' => [
                'interrogatories' => $interrogatoriesCost,
                'document_production' => $documentCost,
                'depositions' => $depositionsTotalCost,
                'expert_fees' => 0,
                'court_costs' => 500,
            ],
        ];
    }

    /**
     * Generate timeline
     */
    protected function generateTimeline(array $facts): array
    {
        return [
            'phase_1' => [
                'days' => 30,
                'description' => 'Initial Written Discovery',
                'activities' => [
                    'Serve interrogatories',
                    'Serve document requests',
                    'Serve requests for admission',
                ],
            ],
            'phase_2' => [
                'days' => 60,
                'description' => 'Document Production and Review',
                'activities' => [
                    'Receive responses to written discovery',
                    'Review produced documents',
                    'Prepare for depositions',
                ],
            ],
            'phase_3' => [
                'days' => 90,
                'description' => 'Depositions',
                'activities' => [
                    'Conduct party depositions',
                    'Conduct witness depositions',
                    'Complete discovery',
                ],
            ],
        ];
    }

    /**
     * Generate formatted discovery document
     */
    public function generateFormattedDocument(string $packageId, string $type): string
    {
        $package = DiscoveryPackage::findOrFail($packageId);
        $factPattern = $package->factPattern;
        $parties = $factPattern->structured_facts['parties'] ?? [];

        return match ($type) {
            'interrogatories' => $this->formatInterrogatories($package->requests['interrogatories'], $parties),
            'document_requests' => $this->formatDocumentRequests($package->requests['document_requests'], $parties),
            'admissions', 'requests_for_admission' => $this->formatAdmissions($package->requests['requests_for_admission'], $parties),
            'deposition_notices' => $this->formatDepositionNotices($package->requests['deposition_notices'], $parties),
            default => throw new \InvalidArgumentException("Unknown document type: {$type}"),
        };
    }

    /**
     * Generate formatted discovery documents
     */
    public function generateFormattedDocuments(string $factPatternId): array
    {
        $package = $this->generateDiscoveryPackage($factPatternId);

        return [
            'interrogatories_text' => $this->formatInterrogatories($package->requests['interrogatories']),
            'document_requests_text' => $this->formatDocumentRequests($package->requests['document_requests']),
            'admissions_text' => $this->formatAdmissions($package->requests['requests_for_admission']),
            'deposition_notices_text' => $this->formatDepositionNotices($package->requests['deposition_notices']),
        ];
    }

    /**
     * Format interrogatories as text
     */
    protected function formatInterrogatories(array $interrogatories, array $parties = []): string
    {
        $text = "INTERROGATORIES\n\n";

        // Add party information if available
        if (! empty($parties)) {
            $plaintiff = collect($parties)->firstWhere('role', 'plaintiff');
            $defendant = collect($parties)->firstWhere('role', 'defendant');

            if ($plaintiff) {
                $text .= "From: {$plaintiff['name']} (Plaintiff)\n";
            }
            if ($defendant) {
                $text .= "To: {$defendant['name']} (Defendant)\n";
            }
            $text .= "\n";
        }

        foreach ($interrogatories['items'] as $item) {
            $text .= "INTERROGATORY NO. {$item['number']}: {$item['text']}\n\n";
        }

        return $text;
    }

    /**
     * Format document requests as text
     */
    protected function formatDocumentRequests(array $requests, array $parties = []): string
    {
        $text = "REQUESTS FOR PRODUCTION OF DOCUMENTS\n\n";

        // Add party information if available
        if (! empty($parties)) {
            $plaintiff = collect($parties)->firstWhere('role', 'plaintiff');
            $defendant = collect($parties)->firstWhere('role', 'defendant');

            if ($plaintiff) {
                $text .= "From: {$plaintiff['name']} (Plaintiff)\n";
            }
            if ($defendant) {
                $text .= "To: {$defendant['name']} (Defendant)\n";
            }
            $text .= "\n";
        }

        foreach ($requests['items'] as $item) {
            $text .= "REQUEST NO. {$item['number']}: {$item['description']}\n\n";
        }

        return $text;
    }

    /**
     * Format admissions as text
     */
    protected function formatAdmissions(array $admissions, array $parties = []): string
    {
        $text = "REQUESTS FOR ADMISSION\n\n";

        // Add party information if available
        if (! empty($parties)) {
            $plaintiff = collect($parties)->firstWhere('role', 'plaintiff');
            $defendant = collect($parties)->firstWhere('role', 'defendant');

            if ($plaintiff) {
                $text .= "From: {$plaintiff['name']} (Plaintiff)\n";
            }
            if ($defendant) {
                $text .= "To: {$defendant['name']} (Defendant)\n";
            }
            $text .= "\n";
        }

        foreach ($admissions['items'] as $item) {
            $text .= "REQUEST NO. {$item['number']}: {$item['statement']}\n\n";
        }

        return $text;
    }

    /**
     * Format deposition notices as text
     */
    protected function formatDepositionNotices(array $notices, array $parties = []): string
    {
        $text = "NOTICES OF DEPOSITION\n\n";

        foreach ($notices['notices'] as $notice) {
            $text .= "DEPONENT: {$notice['deponent']}\n";
            $text .= "TOPICS:\n";
            foreach ($notice['topics'] as $topic) {
                $text .= "  - {$topic}\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}
