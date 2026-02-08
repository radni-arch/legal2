<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\PreventsDuplicateRequests;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class UnifiedSearch extends Component
{
    use PreventsDuplicateRequests;

    // Search parameters
    public string $query = '';

    public array $corpora = ['laws', 'decisions', 'cases'];

    public float $threshold = 0.7;

    public int $limit = 10;

    public int $page = 1;

    public string $sortBy = 'score';

    public string $sortOrder = 'desc';

    public bool $deduplicate = true;

    // Corpus weights (for unified/hybrid search)
    public array $weights = [
        'laws' => 1.0,
        'decisions' => 1.0,
        'cases' => 1.0,
    ];

    // Advanced filters
    public array $filters = [];

    public string $filterJurisdiction = '';

    public string $filterCountry = '';

    public string $filterCourt = '';

    public string $filterLanguage = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    // UI state
    public bool $isSearching = false;

    public bool $showAdvanced = false;

    public bool $showFilters = false;

    public bool $showWeights = false;

    public ?string $error = null;

    // Results
    public ?array $searchResults = null;

    public ?array $searchMetadata = null;

    // Search mode
    public string $searchMode = 'unified'; // unified, hybrid, laws, decisions, cases, with-citations

    protected $queryString = [
        'query' => ['except' => ''],
        'page' => ['except' => 1],
        'searchMode' => ['except' => 'unified'],
    ];

    public function mount()
    {
        // If there's a query in the URL, perform search
        if (! empty($this->query)) {
            $this->search();
        }
    }

    public function updatedQuery()
    {
        // Reset page when query changes
        $this->page = 1;
    }

    public function updatedSearchMode()
    {
        // Reset page when search mode changes
        $this->page = 1;
        // Update corpora based on mode if needed
        if ($this->searchMode === 'laws') {
            $this->corpora = ['laws'];
        } elseif ($this->searchMode === 'decisions') {
            $this->corpora = ['decisions'];
        } elseif ($this->searchMode === 'cases') {
            $this->corpora = ['cases'];
        } elseif (in_array($this->searchMode, ['unified', 'hybrid', 'with-citations'])) {
            $this->corpora = ['laws', 'decisions', 'cases'];
        }
    }

    public function toggleCorpus(string $corpus)
    {
        if (in_array($corpus, $this->corpora)) {
            // Remove if already selected
            $this->corpora = array_values(array_filter($this->corpora, fn ($c) => $c !== $corpus));
        } else {
            // Add if not selected
            $this->corpora[] = $corpus;
        }

        // Ensure at least one corpus is selected
        if (empty($this->corpora)) {
            $this->corpora = ['laws'];
        }

        $this->page = 1;
    }

    public function search()
    {
        $this->validate([
            'query' => 'required|string|min:2|max:1000',
            'threshold' => 'numeric|min:0|max:1',
            'limit' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'filterDateFrom' => 'nullable|date',
            'filterDateTo' => 'nullable|date|after_or_equal:filterDateFrom',
        ]);

        // Prevent duplicate concurrent searches
        return $this->preventDuplicate(
            'search',
            [$this->query, $this->searchMode, $this->page, $this->corpora],
            function () {
                $this->isSearching = true;
                $this->error = null;
                $this->searchResults = null;
                $this->searchMetadata = null;

                try {
                    // Build filters array
                    $filters = [];
                    if (! empty($this->filterJurisdiction)) {
                        $filters['jurisdiction'] = $this->filterJurisdiction;
                    }
                    if (! empty($this->filterCountry)) {
                        $filters['country'] = $this->filterCountry;
                    }
                    if (! empty($this->filterCourt)) {
                        $filters['court'] = $this->filterCourt;
                    }
                    if (! empty($this->filterLanguage)) {
                        $filters['language'] = $this->filterLanguage;
                    }
                    if (! empty($this->filterDateFrom)) {
                        $filters['date_from'] = $this->filterDateFrom;
                    }
                    if (! empty($this->filterDateTo)) {
                        $filters['date_to'] = $this->filterDateTo;
                    }

                    // Build search options
                    $options = [
                        'corpora' => $this->corpora,
                        'weights' => $this->weights,
                        'filters' => $filters,
                        'threshold' => $this->threshold,
                        'limit' => $this->limit,
                        'page' => $this->page,
                        'per_page' => $this->limit,
                        'sort_by' => $this->sortBy,
                        'sort_order' => $this->sortOrder,
                        'deduplicate' => $this->deduplicate,
                    ];

                    // Call service directly - avoids HTTP API authentication requirement
                    $searchService = app(UnifiedSearchService::class);

                    // Determine search method based on search mode
                    $result = match ($this->searchMode) {
                        'hybrid' => $searchService->hybridSearch($this->query, $options),
                        'with-citations' => $searchService->searchWithCitations($this->query, $options),
                        default => $searchService->search($this->query, $options),
                    };

                    if ($result['success'] ?? false) {
                        // Map service response to component format
                        $this->searchResults = $result['data'] ?? [];
                        $metadata = $result['metadata'] ?? [];

                        $this->searchMetadata = [
                            'query' => $metadata['query'] ?? $this->query,
                            'total_results' => $metadata['total_results'] ?? 0,
                            'returned_results' => $metadata['returned_results'] ?? 0,
                            'deduplicated_count' => $metadata['deduplicated_count'] ?? 0,
                            'corpora' => $metadata['corpora_searched'] ?? [],
                            'pagination' => [
                                'page' => $metadata['pagination']['current_page'] ?? 1,
                                'per_page' => $metadata['pagination']['per_page'] ?? $this->limit,
                                'total_pages' => $metadata['pagination']['total_pages'] ?? 0,
                            ],
                            'performance' => $metadata['performance'] ?? [],
                            'request_id' => null,
                            'response_time_ms' => isset($metadata['performance']['total_time'])
                                ? round($metadata['performance']['total_time'] * 1000)
                                : null,
                            'cached' => false,
                            'search_type' => 'vector',
                            'result_counts' => null,
                        ];

                        Log::info('Unified search completed', [
                            'query' => $this->query,
                            'total_results' => $this->searchMetadata['total_results'],
                            'response_time_ms' => $this->searchMetadata['response_time_ms'],
                        ]);
                    } else {
                        $this->error = $result['error'] ?? 'Search request failed';
                    }
                } catch (\Exception $e) {
                    $this->error = 'An error occurred while searching. Please try again.';
                    Log::error('Search exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                } finally {
                    $this->isSearching = false;
                }
            },
            30 // 30 second TTL for search operations
        );
    }

    public function nextPage()
    {
        if ($this->searchMetadata && $this->page < $this->searchMetadata['pagination']['total_pages']) {
            $this->page++;
            $this->search();
        }
    }

    public function previousPage()
    {
        if ($this->page > 1) {
            $this->page--;
            $this->search();
        }
    }

    public function goToPage(int $page)
    {
        if ($page >= 1 && $this->searchMetadata && $page <= $this->searchMetadata['pagination']['total_pages']) {
            $this->page = $page;
            $this->search();
        }
    }

    public function clearSearch()
    {
        $this->query = '';
        $this->page = 1;
        $this->searchResults = null;
        $this->searchMetadata = null;
        $this->error = null;
    }

    public function resetFilters()
    {
        $this->corpora = ['laws', 'decisions', 'cases'];
        $this->threshold = 0.7;
        $this->limit = 10;
        $this->sortBy = 'score';
        $this->sortOrder = 'desc';
        $this->deduplicate = true;
        $this->weights = [
            'laws' => 1.0,
            'decisions' => 1.0,
            'cases' => 1.0,
        ];
        $this->filterJurisdiction = '';
        $this->filterCountry = '';
        $this->filterCourt = '';
        $this->filterLanguage = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->page = 1;
    }

    public function copySearchUrl()
    {
        // This will be handled by JavaScript in the blade view
        $this->dispatch('search-url-copied');
    }

    public function exportResults()
    {
        if (! $this->searchResults) {
            return;
        }

        $filename = 'search-results-'.date('Y-m-d-His').'.json';
        $data = [
            'query' => $this->query,
            'metadata' => $this->searchMetadata,
            'results' => $this->searchResults,
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function groupResultsByType(): array
    {
        if (! $this->searchResults) {
            return [];
        }

        $grouped = [];
        foreach ($this->searchResults as $result) {
            $type = $result['type'] ?? 'unknown';
            if (! isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $result;
        }

        return $grouped;
    }

    public function getTypeLabel(string $type): string
    {
        return match ($type) {
            'law' => 'Laws',
            'decision' => 'Court Decisions',
            'case' => 'Case Documents',
            default => ucfirst($type),
        };
    }

    public function getTypeIcon(string $type): string
    {
        return match ($type) {
            'law' => '⚖️',
            'decision' => '🏛️',
            'case' => '📁',
            default => '📄',
        };
    }

    public function render()
    {
        return view('livewire.unified-search');
    }
}
