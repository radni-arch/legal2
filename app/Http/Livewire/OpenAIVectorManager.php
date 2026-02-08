<?php

namespace App\Http\Livewire;

use App\Jobs\RegenerateFileAttributes;
use App\Models\VectorDocument;
use App\Services\CatalogService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * OpenAIVectorManager Livewire Component
 *
 * Manages vector store operations for OpenAI embeddings including:
 * - Displaying available vector stores
 * - Listing files within a selected store
 * - Viewing and editing file metadata and attributes
 * - Persisting metadata changes to OpenAI API
 *
 * This component provides a UI for managing vector embeddings and
 * vector store operations used for RAG (Retrieval Augmented Generation)
 * and semantic search capabilities.
 */
class OpenAIVectorManager extends Component
{
    /**
     * Collection of available vector stores from OpenAI API
     *
     * @var array<int, array{id: string, name: string, created_at: int, file_count: int, usage_bytes: int}>
     */
    public array $stores = [];

    /**
     * ID of the currently selected vector store
     */
    public ?string $selectedStore = null;

    /**
     * Collection of files in the selected vector store
     *
     * @var array<int, array{id: string, filename: string, created_at: int, size: int, status: string}>
     */
    public array $files = [];

    /**
     * ID of the currently selected file for viewing/editing metadata
     */
    public ?string $selectedFile = null;

    /**
     * Full metadata object for the selected file
     *
     * @var array<string, mixed>
     */
    public array $metadata = [];

    /**
     * File attributes (custom metadata) for the selected file
     *
     * @var array<string, mixed>
     */
    public array $fileAttributes = [];

    /**
     * New metadata in JSON format to be saved for the selected file
     */
    public string $newMetadata = '';

    /**
     * New attributes in JSON format to be saved for the selected file
     */
    public string $newAttributes = '';

    /**
     * Whether to show the page header (false when embedded as nested component)
     */
    public bool $showHeader = true;

    /**
     * Error message if an operation fails
     */
    public ?string $error = null;

    /**
     * Number of entries in the last built catalog
     */
    public int $catalogCount = 0;

    /**
     * Status message from the last catalog operation
     */
    public ?string $catalogStatus = null;

    public int $storesPage = 1;
    public int $storesPerPage = 20;
    public int $storesTotalCount = 0;

    public int $filesPage = 1;
    public int $filesPerPage = 25;
    public int $filesTotalCount = 0;

    /** Cache TTL in seconds (10 minutes for stores, 5 minutes for files) */
    protected int $storesCacheTtl = 600;
    protected int $filesCacheTtl = 300;
    protected int $fileNameCacheTtl = 3600;

    /**
     * Validation rules for form inputs
     *
     * @var array<string, string>
     */
    protected $rules = [
        'newMetadata' => 'nullable|string',
        'newAttributes' => 'nullable|string',
    ];

    /**
     * Initialize component and fetch vector stores from OpenAI API
     */
    public function mount()
    {
        $this->fetchStores();
    }

    /**
     * Fetch all vector stores from OpenAI API using cursor pagination.
     *
     * The OpenAI API returns max 100 items per request with cursor-based
     * pagination. This method fetches all pages and caches the full list.
     */
    public function fetchStores()
    {
        try {
            $service = app(OpenAIService::class);
            $cacheKey = 'openai_vector_stores_all';

            $allStores = Cache::remember($cacheKey, $this->storesCacheTtl, function () use ($service) {
                return $this->fetchAllPaginated(
                    fn(array $query) => $service->vectorStoreList($query)
                );
            });

            $this->storesTotalCount = count($allStores);
            $offset = ($this->storesPage - 1) * $this->storesPerPage;
            $this->stores = array_slice($allStores, $offset, $this->storesPerPage);
            $this->error = null;
        } catch (\Exception $e) {
            Log::warning('OpenAIVectorManager: Failed to fetch stores', ['error' => $e->getMessage()]);
            $this->error = $e->getMessage();
        }
    }

    /**
     * Select a vector store and fetch its files
     */
    public function selectStore($storeId)
    {
        $this->selectedStore = $storeId;
        $this->fetchFiles();
    }

    /**
     * Fetch files from the selected vector store with full pagination.
     *
     * Uses cursor-based pagination to fetch all files (not just first 20),
     * then resolves file names from the OpenAI Files API.
     */
    public function fetchFiles()
    {
        $this->files = [];
        $this->selectedFile = null;
        $this->metadata = [];
        $this->fileAttributes = [];
        $this->filesPage = 1;
        $this->filesTotalCount = 0;

        if (! $this->selectedStore) {
            return;
        }

        try {
            $service = app(OpenAIService::class);
            $cacheKey = "openai_vector_files_{$this->selectedStore}";

            $allFiles = Cache::remember($cacheKey, $this->filesCacheTtl, function () use ($service) {
                $files = $this->fetchAllPaginated(
                    fn(array $query) => $service->vectorStoreListFiles($this->selectedStore, $query)
                );

                return $this->resolveFileNames($service, $files);
            });

            $this->filesTotalCount = count($allFiles);
            $offset = ($this->filesPage - 1) * $this->filesPerPage;
            $this->files = array_slice($allFiles, $offset, $this->filesPerPage);
            $this->error = null;
        } catch (\Exception $e) {
            Log::warning('OpenAIVectorManager: Failed to fetch files', [
                'store' => $this->selectedStore,
                'error' => $e->getMessage(),
            ]);
            $this->error = $e->getMessage();
        }
    }

    /**
     * Select a file and fetch its metadata
     */
    public function selectFile($fileId)
    {
        $this->selectedFile = $fileId;
        $this->fetchFileMeta();
    }

    /**
     * Fetch metadata and attributes for the selected file
     */
    public function fetchFileMeta()
    {
        $this->metadata = [];
        $this->fileAttributes = [];
        if (! $this->selectedFile) {
            return;
        }
        try {
            $service = app(OpenAIService::class);
            $file = $service->vectorStoreGetFile($this->selectedStore, $this->selectedFile);
            $this->metadata = $file ?? [];
            $this->fileAttributes = $file['attributes'] ?? [];
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Delete a file: first detach from the vector store, then delete from OpenAI.
     *
     * OpenAI requires files to be removed from all vector stores before deletion.
     * This method handles the correct order of operations.
     */
    public function deleteFile(string $fileId): void
    {
        if (! $this->selectedStore || ! $fileId) {
            return;
        }

        try {
            $service = app(OpenAIService::class);

            // Step 1: Detach file from vector store (404 means already detached)
            try {
                $service->vectorStoreDeleteFile($this->selectedStore, $fileId);
            } catch (\Exception $e) {
                if (! $this->isNotFoundError($e)) {
                    throw $e;
                }
                Log::info('OpenAIVectorManager: File already detached from store', [
                    'store' => $this->selectedStore,
                    'file' => $fileId,
                ]);
            }

            // Step 2: Delete the file from OpenAI (404 means already deleted)
            try {
                $service->fileDelete($fileId);
            } catch (\Exception $e) {
                if (! $this->isNotFoundError($e)) {
                    throw $e;
                }
                Log::info('OpenAIVectorManager: File already deleted from OpenAI', [
                    'file' => $fileId,
                ]);
            }

            // Clear caches
            Cache::forget("openai_vector_files_{$this->selectedStore}");
            Cache::forget("openai_file_name_{$fileId}");

            // Reset selection if the deleted file was selected
            if ($this->selectedFile === $fileId) {
                $this->selectedFile = null;
                $this->metadata = [];
                $this->fileAttributes = [];
            }

            $this->fetchFiles();
            $this->error = null;
        } catch (\Exception $e) {
            Log::warning('OpenAIVectorManager: Failed to delete file', [
                'store' => $this->selectedStore,
                'file' => $fileId,
                'error' => $e->getMessage(),
            ]);
            $this->error = 'Failed to delete file: ' . $e->getMessage();
        }
    }

    /**
     * Regenerate LLM-reasoned attributes for the selected file.
     *
     * Dispatches RegenerateFileAttributes synchronously, which:
     * 1. Sends the file through OpenAI Responses API with the tagging prompt
     * 2. Extracts structured metadata via function calling (tag_file_metadata)
     * 3. Compacts metadata into flat attributes (keywords, laws, dates, etc.)
     * 4. Saves the new attributes back to the vector store file
     */
    public function regenerateFile(): void
    {
        if (! $this->selectedStore || ! $this->selectedFile) {
            return;
        }

        try {
            RegenerateFileAttributes::dispatchSync(
                $this->selectedStore,
                $this->selectedFile,
            );

            // Invalidate file cache so fresh attributes show
            Cache::forget("openai_vector_files_{$this->selectedStore}");

            // Refresh metadata display
            $this->fetchFileMeta();
            $this->error = null;
        } catch (\Exception $e) {
            Log::warning('OpenAIVectorManager: Failed to regenerate file attributes', [
                'store' => $this->selectedStore,
                'file' => $this->selectedFile,
                'error' => $e->getMessage(),
            ]);
            $this->error = 'Failed to regenerate attributes: ' . $e->getMessage();
        }
    }

    /**
     * Save metadata and attributes for the selected file.
     * Invalidates file cache after successful save.
     */
    public function saveMeta(): void
    {
        $this->validate();

        try {
            $service = app(OpenAIService::class);
            $payload = [];

            if (! empty($this->newMetadata)) {
                $payload['metadata'] = json_decode($this->newMetadata, true);
            }

            if (! empty($this->newAttributes)) {
                $payload['attributes'] = json_decode($this->newAttributes, true);
            }

            $response = $service->vectorStoreFileMetadataUpdate($this->selectedStore, $this->selectedFile, $payload);

            // Invalidate file cache after metadata update
            Cache::forget("openai_vector_files_{$this->selectedStore}");

            $this->fetchFileMeta();
            $this->newMetadata = '';
            $this->newAttributes = '';
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function storesNextPage()
    {
        if ($this->storesPage < $this->storesTotalPages()) {
            $this->storesPage++;
            $this->fetchStores();
        }
    }

    public function storesPreviousPage()
    {
        if ($this->storesPage > 1) {
            $this->storesPage--;
            $this->fetchStores();
        }
    }

    public function storesTotalPages(): int
    {
        return max(1, (int) ceil($this->storesTotalCount / $this->storesPerPage));
    }

    public function filesNextPage()
    {
        if ($this->filesPage < $this->filesTotalPages()) {
            $this->filesPage++;
            $this->loadFilesPage();
        }
    }

    public function filesPreviousPage()
    {
        if ($this->filesPage > 1) {
            $this->filesPage--;
            $this->loadFilesPage();
        }
    }

    public function filesTotalPages(): int
    {
        return max(1, (int) ceil($this->filesTotalCount / $this->filesPerPage));
    }

    protected function loadFilesPage()
    {
        if (! $this->selectedStore) {
            return;
        }

        try {
            $service = app(OpenAIService::class);
            $cacheKey = "openai_vector_files_{$this->selectedStore}";

            $allFiles = Cache::remember($cacheKey, $this->filesCacheTtl, function () use ($service) {
                $files = $this->fetchAllPaginated(
                    fn(array $query) => $service->vectorStoreListFiles($this->selectedStore, $query)
                );

                return $this->resolveFileNames($service, $files);
            });

            $this->filesTotalCount = count($allFiles);
            $offset = ($this->filesPage - 1) * $this->filesPerPage;
            $this->files = array_slice($allFiles, $offset, $this->filesPerPage);
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function refreshStores()
    {
        Cache::forget('openai_vector_stores_all');
        $this->storesPage = 1;
        $this->fetchStores();
    }

    public function refreshFiles()
    {
        if ($this->selectedStore) {
            Cache::forget("openai_vector_files_{$this->selectedStore}");
        }
        $this->filesPage = 1;
        $this->fetchFiles();
    }

    /**
     * Build and upload a catalog for the selected vector store.
     *
     * The catalog is a JSON file containing lean entries for all documents
     * in the store, useful for AI assistants to understand available documents.
     */
    public function buildCatalog(): void
    {
        if (! $this->selectedStore) {
            return;
        }

        try {
            $catalog = app(CatalogService::class);
            $result = $catalog->buildAndUploadCatalog($this->selectedStore);

            $this->catalogCount = $result['entries'];
            $this->catalogStatus = "Catalog built: {$result['entries']} entries, file_id: {$result['file_id']}";

            // Invalidate file cache so the new catalog file appears
            Cache::forget("openai_vector_files_{$this->selectedStore}");

            // Refresh the files list
            $this->dispatch('catalogBuilt');
            $this->fetchFiles();
        } catch (\Exception $e) {
            Log::warning('OpenAIVectorManager: Failed to build catalog', [
                'store' => $this->selectedStore,
                'error' => $e->getMessage(),
            ]);
            $this->catalogStatus = 'Catalog build failed: '.$e->getMessage();
        }
    }

    /**
     * Get document status from local database by OpenAI file ID.
     */
    public function getDocumentStatus(string $fileId): ?string
    {
        return VectorDocument::where('openai_file_id', $fileId)->value('status');
    }

    /**
     * Get catalog status counts for the selected vector store.
     *
     * Returns counts of documents grouped by status (pending, tagged, uploaded, cataloged).
     */
    public function getCatalogStats(): array
    {
        if (! $this->selectedStore) {
            return [];
        }

        return app(CatalogService::class)->getStatusCounts($this->selectedStore);
    }

    /**
     * Fetch all items from an OpenAI list endpoint using cursor-based pagination.
     *
     * OpenAI list endpoints return max 100 items per request with `has_more`
     * and `last_id` for cursor-based pagination. This method iterates through
     * all pages until `has_more` is false.
     *
     * @param  callable  $fetcher  Function accepting query array, returning API response
     * @return array All items across all pages
     */
    protected function fetchAllPaginated(callable $fetcher): array
    {
        $allItems = [];
        $after = null;
        $maxPages = 50; // Safety limit to avoid infinite loops

        for ($page = 0; $page < $maxPages; $page++) {
            $query = ['limit' => 100];
            if ($after) {
                $query['after'] = $after;
            }

            $response = $fetcher($query);
            $data = $response['data'] ?? [];

            if (empty($data)) {
                break;
            }

            array_push($allItems, ...$data);

            $hasMore = $response['has_more'] ?? false;
            if (! $hasMore) {
                break;
            }

            $after = $response['last_id'] ?? end($data)['id'] ?? null;
            if (! $after) {
                break;
            }
        }

        return $allItems;
    }

    /**
     * Resolve human-readable file names for vector store files.
     *
     * Vector store file entries only contain a file ID. This method looks up
     * the actual filename from the OpenAI Files API and caches results
     * individually for 1 hour.
     *
     * @param  OpenAIService  $service
     * @param  array  $files  Vector store file entries
     * @return array Files with `resolved_name` added
     */
    protected function resolveFileNames(OpenAIService $service, array $files): array
    {
        foreach ($files as &$file) {
            $fileId = $file['id'] ?? null;
            if (! $fileId) {
                continue;
            }

            // Check if there's already a filename
            if (! empty($file['filename']) && $file['filename'] !== 'Unnamed File') {
                $file['resolved_name'] = $file['filename'];
                continue;
            }

            // Look up from OpenAI Files API with per-file caching
            $nameCacheKey = "openai_file_name_{$fileId}";
            $resolvedName = Cache::remember($nameCacheKey, $this->fileNameCacheTtl, function () use ($service, $fileId) {
                try {
                    $fileInfo = $service->fileRetrieve($fileId);

                    return $fileInfo['filename'] ?? null;
                } catch (\Exception $e) {
                    Log::debug('OpenAIVectorManager: Could not resolve filename', [
                        'file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            });

            $file['resolved_name'] = $resolvedName ?? $fileId;
        }

        return $files;
    }

    /**
     * Check if an exception represents a 404 Not Found error.
     */
    protected function isNotFoundError(\Exception $e): bool
    {
        // Check for HTTP 404 status in the error message (from CircuitBreakerException wrapping)
        if (str_contains($e->getMessage(), 'status code 404')) {
            return true;
        }

        // Check for RequestException with 404 status
        if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response?->status() === 404) {
            return true;
        }

        // Check wrapped exceptions
        $previous = $e->getPrevious();
        if ($previous instanceof \Illuminate\Http\Client\RequestException && $previous->response?->status() === 404) {
            return true;
        }

        return false;
    }

    /**
     * Render the Livewire component view
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('livewire.openai-vector-manager')
            ->layout('components.layouts.app', ['title' => 'OpenAI Vector Stores Manager']);
    }
}
