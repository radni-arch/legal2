<?php

namespace App\Http\Livewire;

use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use App\Services\Esljp\EsljpIngestService;
use App\Services\Usud\UsudIngestService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Decision Discovery Dashboard
 *
 * Manual search and ingestion interface for court decisions (Odluke + ESLJP).
 *
 * Features:
 * - Search form with keywords and filters
 * - Results table with pagination
 * - Preview modal for decision details
 * - Batch selection for ingesting multiple decisions
 * - Progress tracking during ingestion
 * - Statistics dashboard
 */
class DecisionDiscoveryDashboard extends Component
{
    use WithPagination;

    // Search parameters
    public $searchKeywords = '';

    public $sourceType = 'odluke';

    public $courtFilter = '';

    public $decisionType = '';

    public $dateFrom = '';

    public $dateTo = '';

    // Search results
    public $searchResults = [];

    public $searchPerformed = false;

    public $searchError = null;

    // Selection
    public $selectedDecisions = [];

    public $selectAll = false;

    // Preview
    public $previewDecisionId = null;

    public $previewData = null;

    public $showPreviewModal = false;

    // Ingest progress
    public $ingestInProgress = false;

    public $ingestProgress = 0;

    public $ingestTotal = 0;

    public $ingestSucceeded = 0;

    public $ingestFailed = 0;

    public $ingestErrors = [];

    // UI state
    public $successMessage = null;

    public $errorMessage = null;

    // Options
    public $sourceOptions = [
        'odluke' => 'Odluke.sudovi.hr',
        'esljp' => 'ESLJP (sljeme.usud.hr)',
        'usud' => 'Ustavni sud (sljeme.usud.hr)',
    ];

    public $courtOptions = [
        '' => 'All Courts',
        'Vrhovni sud' => 'Supreme Court (Vrhovni sud)',
        'Visoki kazneni sud' => 'High Criminal Court (Visoki kazneni sud)',
        'Županijski sud' => 'County Court (Županijski sud)',
        'Općinski sud' => 'Municipal Court (Općinski sud)',
    ];

    public $decisionTypeOptions = [
        '' => 'All Types',
        'Presuda' => 'Judgment (Presuda)',
        'Rješenje' => 'Decision (Rješenje)',
        'Zaključak' => 'Conclusion (Zaključak)',
    ];

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        // Initialize date filters to reasonable defaults
        $this->dateTo = now()->format('Y-m-d');
        $this->dateFrom = now()->subMonths(6)->format('Y-m-d');
    }

    public function updatedSourceType($value = null)
    {
        $this->resetSearch();
    }

    /**
     * Perform search on selected decision source
     */
    public function search()
    {
        $rules = [
            'searchKeywords' => 'required|min:3',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date|after_or_equal:dateFrom',
        ];

        $this->validate($rules, [
            'searchKeywords.required' => 'Please enter search keywords',
            'searchKeywords.min' => 'Keywords must be at least 3 characters',
            'dateTo.after_or_equal' => 'End date must be after start date',
        ]);

        try {
            $this->searchPerformed = true;
            $this->searchError = null;
            $this->searchResults = [];
            $this->selectedDecisions = [];

            if ($this->sourceType === 'usud') {
                $ingestService = app(UsudIngestService::class);
                $result = $ingestService->searchList($this->searchKeywords);

                if (isset($result['error'])) {
                    $this->searchError = $result['error'];

                    return;
                }

                $items = $result['items'] ?? [];
                if (empty($items)) {
                    $this->searchError = 'No Constitutional Court decisions found matching your criteria';

                    return;
                }

                $this->searchResults = collect($items)
                    ->map(function ($item) {
                        return [
                            'id' => $item['id'],
                            'case_number' => $item['case_number'] ?? 'N/A',
                            'court' => $item['court'] ?? 'USUD',
                            'decision_date' => $item['decision_date'] ?? 'N/A',
                            'decision_type' => $item['decision_type'] ?? 'N/A',
                            'ecli' => null,
                            'meta' => [
                                'title' => $item['title'] ?? null,
                                'detail_url' => $item['detail_url'] ?? null,
                                'pdf_url' => $item['pdf_url'] ?? null,
                            ],
                        ];
                    })
                    ->values()
                    ->toArray();

                $this->successMessage = 'Found '.count($this->searchResults).' Constitutional Court decisions';

                return;
            }

            if ($this->sourceType === 'esljp') {
                $ingestService = app(EsljpIngestService::class);
                $result = $ingestService->searchList($this->searchKeywords, [
                    'count' => 200,
                ]);

                if (isset($result['error'])) {
                    $this->searchError = $result['error'];

                    return;
                }

                $items = $result['items'] ?? [];
                if (empty($items)) {
                    $this->searchError = 'No ESLJP decisions found matching your criteria';

                    return;
                }

                $this->searchResults = collect($items)
                    ->map(function ($item) {
                        return [
                            'id' => $item['id'],
                            'case_number' => $item['case_number'] ?? 'N/A',
                            'court' => $item['court'] ?? 'ESLJP',
                            'decision_date' => $item['decision_date'] ?? 'N/A',
                            'decision_type' => $item['decision_type'] ?? 'N/A',
                            'ecli' => null,
                            'meta' => [
                                'title' => $item['title'] ?? null,
                                'detail_url' => $item['detail_url'] ?? null,
                            ],
                        ];
                    })
                    ->values()
                    ->toArray();

                $this->successMessage = 'Found '.count($this->searchResults).' ESLJP decisions';

                return;
            }

            $client = OdlukeClient::fromConfig();

            // Build search parameters
            $params = $this->buildSearchParams();

            // Collect decision IDs from search results
            $result = $client->collectIdsFromList(
                $this->searchKeywords,
                $params,
                limit: 50,
                page: 1
            );

            if (isset($result['error'])) {
                $this->searchError = $result['error'];

                return;
            }

            $ids = $result['ids'] ?? [];

            if (empty($ids)) {
                $this->searchError = 'No decisions found matching your criteria';

                return;
            }

            // Fetch metadata for each decision
            $ingestService = app(OdlukeIngestService::class);
            $decisionsWithMeta = $ingestService->getMetadataForIds($ids);

            $this->searchResults = collect($decisionsWithMeta)
                ->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'case_number' => $item['meta']['broj_odluke'] ?? 'N/A',
                        'court' => $item['meta']['sud'] ?? 'N/A',
                        'decision_date' => $item['meta']['datum_odluke'] ?? 'N/A',
                        'decision_type' => $item['meta']['vrsta_odluke'] ?? 'N/A',
                        'ecli' => $item['meta']['ecli'] ?? null,
                        'meta' => $item['meta'] ?? [],
                    ];
                })
                ->filter(function ($decision) {
                    // Apply client-side filters
                    if ($this->courtFilter && ! str_contains(strtolower($decision['court']), strtolower($this->courtFilter))) {
                        return false;
                    }
                    if ($this->decisionType && ! str_contains(strtolower($decision['decision_type']), strtolower($this->decisionType))) {
                        return false;
                    }

                    return true;
                })
                ->values()
                ->toArray();

            $this->successMessage = 'Found '.count($this->searchResults).' decisions';

        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryDashboard] Search failed', [
                'keywords' => $this->searchKeywords,
                'error' => $e->getMessage(),
            ]);

            $this->searchError = 'Search failed: '.$e->getMessage();
        }
    }

    /**
     * Build search parameters for OdlukeClient
     */
    protected function buildSearchParams(): string
    {
        $params = [];

        if ($this->dateFrom) {
            $params[] = 'dateFrom='.urlencode($this->dateFrom);
        }

        if ($this->dateTo) {
            $params[] = 'dateTo='.urlencode($this->dateTo);
        }

        return implode('&', $params);
    }

    /**
     * Preview decision details
     */
    public function preview($decisionId)
    {
        try {
            $this->previewDecisionId = $decisionId;

            // Find decision in search results
            $decision = collect($this->searchResults)
                ->firstWhere('id', $decisionId);

            if (! $decision) {
                $this->errorMessage = 'Decision not found';

                return;
            }

            $this->previewData = $decision;
            $this->showPreviewModal = true;

        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryDashboard] Preview failed', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Failed to load preview: '.$e->getMessage();
        }
    }

    /**
     * Close preview modal
     */
    public function closePreview()
    {
        $this->showPreviewModal = false;
        $this->previewDecisionId = null;
        $this->previewData = null;
    }

    /**
     * Select decision for ingestion
     */
    public function selectForIngest($decisionId)
    {
        if (in_array($decisionId, $this->selectedDecisions)) {
            // Deselect
            $this->selectedDecisions = array_values(
                array_filter($this->selectedDecisions, fn ($id) => $id !== $decisionId)
            );
        } else {
            // Select
            $this->selectedDecisions[] = $decisionId;
        }

        $this->selectAll = count($this->selectedDecisions) === count($this->searchResults);
    }

    /**
     * Toggle select all
     */
    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedDecisions = collect($this->searchResults)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedDecisions = [];
        }
    }

    /**
     * Ingest selected decisions
     */
    public function ingestSelected()
    {
        if (empty($this->selectedDecisions)) {
            $this->errorMessage = 'Please select at least one decision to ingest';

            return;
        }

        try {
            $this->ingestInProgress = true;
            $this->ingestProgress = 0;
            $this->ingestTotal = count($this->selectedDecisions);
            $this->ingestSucceeded = 0;
            $this->ingestFailed = 0;
            $this->ingestErrors = [];
            $this->errorMessage = null;
            $this->successMessage = null;

            $ingestService = match ($this->sourceType) {
                'esljp' => app(EsljpIngestService::class),
                'usud' => app(UsudIngestService::class),
                default => app(OdlukeIngestService::class),
            };

            // Use queued ingestion for better performance
            $result = $ingestService->ingestByIds($this->selectedDecisions, [
                'queue' => true,
                'queue_name' => 'default',
                'sync_graph' => $this->sourceType === 'odluke',
            ]);

            $this->ingestSucceeded = $result['queued'] ?? 0;
            $this->ingestFailed = $result['errors'] ?? 0;
            $this->ingestProgress = $this->ingestTotal;

            $this->successMessage = sprintf(
                'Successfully queued %d decisions for ingestion. Check queue status for progress.',
                $this->ingestSucceeded
            );

            // Clear selection
            $this->selectedDecisions = [];
            $this->selectAll = false;

            // Refresh stats
            $this->refreshStats();

        } catch (\Throwable $e) {
            Log::error('[DecisionDiscoveryDashboard] Ingest failed', [
                'selected_count' => count($this->selectedDecisions),
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Ingestion failed: '.$e->getMessage();

        } finally {
            $this->ingestInProgress = false;
        }
    }

    /**
     * Refresh statistics
     */
    public function refreshStats()
    {
        Cache::forget('decision_discovery_stats');
    }

    /**
     * Reset search
     */
    public function resetSearch()
    {
        $this->searchKeywords = '';
        $this->courtFilter = '';
        $this->decisionType = '';
        $this->dateFrom = now()->subMonths(6)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->searchResults = [];
        $this->searchPerformed = false;
        $this->searchError = null;
        $this->selectedDecisions = [];
        $this->selectAll = false;
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $stats = Cache::remember('decision_discovery_stats', 60, function () {
            // Get ingestion statistics from court_decision_documents
            $totalDecisions = \DB::table('court_decisions')->count();
            $totalChunks = \DB::table('court_decision_documents')->count();
            $decisionsWithVectors = \DB::table('court_decision_documents')
                ->distinct('decision_id')
                ->count('decision_id');

            return [
                'total_decisions' => $totalDecisions,
                'total_chunks' => $totalChunks,
                'decisions_with_vectors' => $decisionsWithVectors,
                'avg_chunks_per_decision' => $totalDecisions > 0
                    ? round($totalChunks / $totalDecisions, 1)
                    : 0,
            ];
        });

        return view('livewire.decision-discovery-dashboard', [
            'stats' => $stats,
        ]);
    }
}
