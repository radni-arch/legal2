<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\PreventsDuplicateRequests;
use App\Services\VectorStoreManagementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vector Store Manager
 *
 * Unified management interface for all vector stores:
 * - Laws
 * - Court Decisions
 * - Cases
 * - Textract
 *
 * Features:
 * - View statistics
 * - Browse documents (paginated)
 * - Search by similarity or doc_id
 * - Re-index documents
 * - Delete documents
 */
class VectorStoreManager extends Component
{
    use PreventsDuplicateRequests;
    use WithPagination;

    // Store selection
    public $selectedStore = 'laws';

    public $stores = [];

    // Document browsing
    public $documents = [];

    public $currentPage = 1;

    public $perPage = 20;

    public $totalDocuments = 0;

    public $totalPages = 0;

    // Search
    public $searchQuery = '';

    public $searchType = 'content'; // content, similarity, doc_id

    public $searchResults = [];

    public $showingSearchResults = false;

    // Bulk selection
    public $selectedDocuments = [];

    public $selectAll = false;

    // Document preview
    public $previewDocumentData = null;

    public $showPreviewModal = false;

    // UI state
    public $successMessage = null;

    public $errorMessage = null;

    public $loading = false;

    // Statistics
    public $stats = [];

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $service = app(VectorStoreManagementService::class);
        $this->stores = $service->getStores();
        $this->loadDocuments();
        $this->loadStats();
    }

    /**
     * Load documents for current store
     */
    public function loadDocuments()
    {
        $this->successMessage = null;
        $this->loading = true;

        try {
            $service = app(VectorStoreManagementService::class);
            $result = $service->browseDocuments(
                $this->selectedStore,
                $this->currentPage,
                $this->perPage,
                $this->searchQuery
            );

            $this->documents = $result['data'];
            $this->totalDocuments = $result['total'];
            $this->totalPages = $result['total_pages'];

            $this->showingSearchResults = false;
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManager] Failed to load documents', [
                'store' => $this->selectedStore,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Failed to load documents: '.$e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Load statistics for current store
     */
    public function loadStats()
    {
        try {
            $service = app(VectorStoreManagementService::class);
            $this->stats = Cache::remember(
                "vector_store_stats_{$this->selectedStore}",
                60,
                fn () => $service->getStatistics($this->selectedStore)
            );
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManager] Failed to load stats', [
                'store' => $this->selectedStore,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Change selected store
     */
    public function selectStore($storeKey)
    {
        $this->selectedStore = $storeKey;
        $this->currentPage = 1;
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showingSearchResults = false;
        $this->selectedDocuments = [];
        $this->selectAll = false;
        $this->errorMessage = null;
        $this->successMessage = null;

        $this->loadDocuments();
        $this->loadStats();
    }

    /**
     * Perform search
     */
    public function search()
    {
        // Clear previous messages
        $this->errorMessage = null;
        $this->successMessage = null;

        // Trim and validate search query
        $this->searchQuery = trim($this->searchQuery);

        if (empty($this->searchQuery)) {
            $this->errorMessage = 'Please enter a search query';

            return;
        }

        $this->loading = true;

        try {
            $service = app(VectorStoreManagementService::class);

            if ($this->searchType === 'similarity') {
                // Similarity search using embeddings
                $this->searchResults = $service->searchBySimilarity(
                    $this->selectedStore,
                    $this->searchQuery,
                    limit: 20
                );
                $this->showingSearchResults = true;
                $this->successMessage = 'Found '.count($this->searchResults).' similar documents';
            } else {
                // Content/doc_id search (already handled by loadDocuments)
                $this->loadDocuments();
            }
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManager] Search failed', [
                'store' => $this->selectedStore,
                'query' => $this->searchQuery,
                'type' => $this->searchType,
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Search failed: '.$e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Reset search
     */
    public function resetSearch()
    {
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showingSearchResults = false;
        $this->loadDocuments();
    }

    /**
     * Go to page
     */
    public function gotoPage($page)
    {
        $this->currentPage = max(1, min($page, $this->totalPages));
        $this->loadDocuments();
    }

    /**
     * Next page
     */
    public function nextPage()
    {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
            $this->loadDocuments();
        }
    }

    /**
     * Previous page
     */
    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->loadDocuments();
        }
    }

    /**
     * Preview document
     */
    public function previewDocument($documentId)
    {
        try {
            $service = app(VectorStoreManagementService::class);
            $this->previewDocumentData = $service->getDocument($this->selectedStore, $documentId);
            $this->showPreviewModal = true;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to load document: '.$e->getMessage();
        }
    }

    /**
     * Close preview modal
     */
    public function closePreview()
    {
        $this->showPreviewModal = false;
        $this->previewDocumentData = null;
    }

    /**
     * Toggle document selection
     */
    public function toggleSelection($documentId)
    {
        if (in_array($documentId, $this->selectedDocuments)) {
            $this->selectedDocuments = array_values(
                array_filter($this->selectedDocuments, fn ($id) => $id !== $documentId)
            );
        } else {
            $this->selectedDocuments[] = $documentId;
        }

        $this->selectAll = count($this->selectedDocuments) === count($this->documents);
    }

    /**
     * Toggle select all
     */
    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedDocuments = collect($this->documents)->pluck('id')->toArray();
        } else {
            $this->selectedDocuments = [];
        }
    }

    /**
     * Delete selected documents
     */
    public function deleteSelected()
    {
        if (empty($this->selectedDocuments)) {
            $this->errorMessage = 'Please select at least one document to delete';

            return;
        }

        // Prevent duplicate delete operations (critical to avoid data corruption)
        return $this->preventDuplicate(
            'deleteSelected',
            [$this->selectedStore, $this->selectedDocuments],
            function () {
                try {
                    $service = app(VectorStoreManagementService::class);
                    $deleted = 0;

                    foreach ($this->selectedDocuments as $documentId) {
                        if ($service->deleteDocument($this->selectedStore, $documentId)) {
                            $deleted++;
                        }
                    }

                    $this->successMessage = "Successfully deleted {$deleted} documents";
                    $this->selectedDocuments = [];
                    $this->selectAll = false;

                    $this->loadDocuments();
                    $this->loadStats();
                } catch (\Throwable $e) {
                    Log::error('[VectorStoreManager] Bulk delete failed', [
                        'store' => $this->selectedStore,
                        'count' => count($this->selectedDocuments),
                        'error' => $e->getMessage(),
                    ]);

                    $this->errorMessage = 'Failed to delete documents: '.$e->getMessage();
                }
            },
            60 // 60 second TTL for bulk operations
        );
    }

    /**
     * Re-index selected documents
     */
    public function reindexSelected()
    {
        if (empty($this->selectedDocuments)) {
            $this->errorMessage = 'Please select at least one document to re-index';

            return;
        }

        try {
            $service = app(VectorStoreManagementService::class);
            $reindexed = 0;

            foreach ($this->selectedDocuments as $documentId) {
                if ($service->reindexDocument($this->selectedStore, $documentId)) {
                    $reindexed++;
                }
            }

            $this->successMessage = "Successfully re-indexed {$reindexed} documents";
            $this->selectedDocuments = [];
            $this->selectAll = false;

            $this->loadDocuments();
            $this->loadStats();
        } catch (\Throwable $e) {
            Log::error('[VectorStoreManager] Bulk re-index failed', [
                'store' => $this->selectedStore,
                'count' => count($this->selectedDocuments),
                'error' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Failed to re-index documents: '.$e->getMessage();
        }
    }

    /**
     * Delete single document
     */
    public function deleteDocument($documentId)
    {
        try {
            $service = app(VectorStoreManagementService::class);

            if ($service->deleteDocument($this->selectedStore, $documentId)) {
                $this->successMessage = 'Document deleted successfully';
                $this->loadDocuments();
                $this->loadStats();
            } else {
                $this->errorMessage = 'Failed to delete document';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to delete document: '.$e->getMessage();
        }
    }

    /**
     * Re-index single document
     */
    public function reindexDocument($documentId)
    {
        try {
            $service = app(VectorStoreManagementService::class);

            if ($service->reindexDocument($this->selectedStore, $documentId)) {
                $this->successMessage = 'Document re-indexed successfully';
                $this->loadDocuments();
            } else {
                $this->errorMessage = 'Failed to re-index document';
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to re-index document: '.$e->getMessage();
        }
    }

    /**
     * Refresh statistics
     */
    public function refreshStats()
    {
        $this->errorMessage = null;
        Cache::forget("vector_store_stats_{$this->selectedStore}");
        $this->loadStats();
        $this->successMessage = 'Statistics refreshed';
    }

    public function render()
    {
        return view('livewire.vector-store-manager')
            ->layout('components.layouts.app', ['title' => 'Vector Store Manager']);
    }
}
