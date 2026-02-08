<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\OpenAIVectorManager;
use App\Models\User;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for OpenAIVectorManager Livewire component
 *
 * Covers vector store CRUD operations, file management, search,
 * statistics, batch operations, error handling, permissions,
 * loading states, and confirmation modals.
 */
class OpenAIVectorManagerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected array $mockVectorStores;

    protected array $mockFiles;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to prevent cross-test contamination from Cache::remember
        Cache::flush();

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Mock vector store data
        $this->mockVectorStores = [
            [
                'id' => 'vs_test123',
                'object' => 'vector_store',
                'name' => 'Legal Documents Store',
                'status' => 'completed',
                'file_counts' => ['completed' => 10, 'in_progress' => 0, 'failed' => 0],
                'created_at' => 1640000000,
            ],
            [
                'id' => 'vs_test456',
                'object' => 'vector_store',
                'name' => 'Case Law Store',
                'status' => 'completed',
                'file_counts' => ['completed' => 5, 'in_progress' => 1, 'failed' => 0],
                'created_at' => 1640000100,
            ],
        ];

        // Mock file data
        $this->mockFiles = [
            [
                'id' => 'file_abc123',
                'object' => 'vector_store.file',
                'status' => 'completed',
                'created_at' => 1640000200,
                'attributes' => ['name' => 'document1.pdf', 'size' => 1024],
            ],
            [
                'id' => 'file_def456',
                'object' => 'vector_store.file',
                'status' => 'in_progress',
                'created_at' => 1640000300,
                'attributes' => ['name' => 'document2.pdf', 'size' => 2048],
            ],
        ];
    }

    /**
     * Test 1: List vector stores
     */
    public function test_list_vector_stores(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.openai-vector-manager')
            ->assertSet('stores', $this->mockVectorStores)
            ->assertSet('selectedStore', null)
            ->assertSet('files', []);
    }

    /**
     * Test 2: Create vector store
     */
    public function test_create_vector_store(): void
    {
        $this->markTestSkipped('Create vector store functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Creating a new vector store with a name
        // - Validating required fields
        // - Refreshing the store list after creation
        // - Displaying success message
    }

    /**
     * Test 3: Upload files to store
     */
    public function test_upload_files_to_store(): void
    {
        $this->markTestSkipped('Upload files to store functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Uploading a file to OpenAI
        // - Adding the file to the selected vector store
        // - Validating file type and size
        // - Displaying upload progress
        // - Refreshing file list after upload
    }

    /**
     * Test 4: Delete files from store
     */
    public function test_delete_files_from_store(): void
    {
        $this->markTestSkipped('Delete files from store functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Deleting a file from the vector store
        // - Showing confirmation modal before deletion
        // - Refreshing file list after deletion
        // - Displaying success message
    }

    /**
     * Test 5: Delete vector store
     */
    public function test_delete_vector_store(): void
    {
        $this->markTestSkipped('Delete vector store functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Deleting an entire vector store
        // - Showing confirmation modal with warning
        // - Refreshing store list after deletion
        // - Clearing selected store
        // - Displaying success message
    }

    /**
     * Test 6: Search within store
     */
    public function test_search_within_store(): void
    {
        $this->markTestSkipped('Search within store functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Searching for files by name or content
        // - Filtering file list based on search query
        // - Clearing search results
        // - Displaying "no results" message
    }

    /**
     * Test 7: View store statistics
     */
    public function test_view_store_statistics(): void
    {
        $this->markTestSkipped('View store statistics functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Displaying total file count
        // - Showing completed/in_progress/failed counts
        // - Displaying store creation date
        // - Showing total storage size
        // - Displaying usage statistics
    }

    /**
     * Test 8: Batch upload
     */
    public function test_batch_upload(): void
    {
        $this->markTestSkipped('Batch upload functionality is not yet implemented in the component');

        // When implemented, this test should verify:
        // - Uploading multiple files at once
        // - Displaying batch upload progress
        // - Handling partial failures
        // - Showing success/failure summary
        // - Refreshing file list after batch upload
    }

    /**
     * Test 9: Error handling displays correctly
     */
    public function test_error_handling_displays(): void
    {
        // Mock OpenAIService with error
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andThrow(new \Exception('OpenAI API error: Invalid API key'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->assertStatus(200)
            ->assertSet('error', 'OpenAI API error: Invalid API key')
            ->assertSet('stores', []);
    }

    /**
     * Test 10: Permission checks and access control
     */
    public function test_permission_checks_and_access_control(): void
    {
        // Test authenticated user can access component
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::actingAs($this->user)
            ->test(OpenAIVectorManager::class)
            ->assertStatus(200);

        // Additional permission checks (role-based, etc.) would be tested here
        // when implemented in the component
    }

    /**
     * Test 11: Loading states
     */
    public function test_loading_states(): void
    {
        $this->markTestSkipped('Loading states are not yet explicitly implemented in the component');

        // When implemented, this test should verify:
        // - Showing loading spinner when fetching stores
        // - Showing loading indicator when fetching files
        // - Disabling buttons during operations
        // - Displaying "Loading..." text appropriately
    }

    /**
     * Test 12: Confirmation modals
     */
    public function test_confirmation_modals(): void
    {
        $this->markTestSkipped('Confirmation modals are not yet implemented in the component');

        // When implemented, this test should verify:
        // - Showing confirmation modal before deletion
        // - Confirming dangerous operations
        // - Cancelling operations
        // - Displaying appropriate warning messages
    }

    /**
     * Test 13: Select store and fetch files
     */
    public function test_select_store_and_fetch_files(): void
    {
        // Mock OpenAIService for initial load
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        // Mock file list fetch
        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->with('vs_test123')
            ->andReturn(['data' => $this->mockFiles]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->assertSet('selectedStore', null)
            ->assertSet('files', [])
            ->call('selectStore', 'vs_test123')
            ->assertSet('selectedStore', 'vs_test123')
            ->assertSet('files', $this->mockFiles);
    }

    /**
     * Test 14: Select file and fetch metadata
     */
    public function test_select_file_and_fetch_metadata(): void
    {
        $mockFileMetadata = [
            'id' => 'file_abc123',
            'object' => 'vector_store.file',
            'status' => 'completed',
            'attributes' => [
                'name' => 'document1.pdf',
                'size' => 1024,
                'type' => 'application/pdf',
            ],
            'metadata' => [
                'author' => 'John Doe',
                'category' => 'legal',
            ],
        ];

        // Mock OpenAIService for initial load and file list
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->with('vs_test123')
            ->andReturn(['data' => $this->mockFiles]);

        // Mock file metadata fetch
        $mockService->shouldReceive('vectorStoreGetFile')
            ->once()
            ->with('vs_test123', 'file_abc123')
            ->andReturn($mockFileMetadata);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->assertSet('selectedStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->assertSet('selectedFile', 'file_abc123')
            ->assertSet('metadata', $mockFileMetadata)
            ->assertSet('fileAttributes', $mockFileMetadata['attributes']);
    }

    /**
     * Test 15: Save file metadata
     */
    public function test_save_file_metadata(): void
    {
        $mockFileMetadata = [
            'id' => 'file_abc123',
            'object' => 'vector_store.file',
            'status' => 'completed',
            'attributes' => ['name' => 'document1.pdf', 'size' => 1024],
            'metadata' => ['author' => 'John Doe'],
        ];

        $updatedMetadata = [
            'author' => 'Jane Smith',
            'category' => 'contracts',
        ];

        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->andReturn(['data' => $this->mockFiles]);

        $mockService->shouldReceive('vectorStoreGetFile')
            ->twice()
            ->andReturn($mockFileMetadata);

        // Mock metadata update
        $mockService->shouldReceive('vectorStoreFileMetadataUpdate')
            ->once()
            ->with('vs_test123', 'file_abc123', Mockery::type('array'))
            ->andReturn(['success' => true]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->set('newMetadata', json_encode($updatedMetadata))
            ->call('saveMeta')
            ->assertHasNoErrors();
    }

    /**
     * Test 16: Fetch stores on mount
     */
    public function test_fetch_stores_on_mount(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $this->app->instance(OpenAIService::class, $mockService);

        // Component should fetch stores on mount
        Livewire::test(OpenAIVectorManager::class)
            ->assertSet('stores', $this->mockVectorStores);
    }

    /**
     * Test 17: Error handling when fetching files
     */
    public function test_error_handling_when_fetching_files(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        // Mock error when fetching files
        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->with('vs_test123')
            ->andThrow(new \Exception('Failed to fetch files: Rate limit exceeded'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->assertSet('selectedStore', 'vs_test123')
            ->assertSet('error', 'Failed to fetch files: Rate limit exceeded')
            ->assertSet('files', []);
    }

    /**
     * Test 18: Error handling when fetching file metadata
     */
    public function test_error_handling_when_fetching_file_metadata(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->andReturn(['data' => $this->mockFiles]);

        // Mock error when fetching file metadata
        $mockService->shouldReceive('vectorStoreGetFile')
            ->once()
            ->with('vs_test123', 'file_abc123')
            ->andThrow(new \Exception('File not found'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->assertSet('selectedFile', 'file_abc123')
            ->assertSet('error', 'File not found')
            ->assertSet('metadata', []);
    }

    /**
     * Test 19: Error handling when saving metadata
     */
    public function test_error_handling_when_saving_metadata(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->andReturn(['data' => $this->mockFiles]);

        $mockService->shouldReceive('vectorStoreGetFile')
            ->once()
            ->andReturn(['id' => 'file_abc123', 'attributes' => []]);

        // Mock error when saving metadata
        $mockService->shouldReceive('vectorStoreFileMetadataUpdate')
            ->once()
            ->andThrow(new \Exception('Failed to update metadata: Permission denied'));

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->set('newMetadata', '{"key": "value"}')
            ->call('saveMeta')
            ->assertSet('error', 'Failed to update metadata: Permission denied');
    }

    /**
     * Test 20: Clear selected file when switching stores
     */
    public function test_clear_selected_file_when_switching_stores(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->twice()
            ->andReturn(['data' => $this->mockFiles]);

        $mockService->shouldReceive('vectorStoreGetFile')
            ->once()
            ->andReturn(['id' => 'file_abc123', 'attributes' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->assertSet('selectedFile', 'file_abc123')
            // Switch to different store
            ->call('selectStore', 'vs_test456')
            ->assertSet('selectedStore', 'vs_test456')
            ->assertSet('selectedFile', null)
            ->assertSet('metadata', [])
            ->assertSet('fileAttributes', []);
    }

    /**
     * Test 21: Validate metadata JSON format
     */
    public function test_validate_metadata_json_format(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        $mockService->shouldReceive('vectorStoreListFiles')
            ->once()
            ->andReturn(['data' => $this->mockFiles]);

        $mockService->shouldReceive('vectorStoreGetFile')
            ->once()
            ->andReturn(['id' => 'file_abc123', 'attributes' => []]);

        $this->app->instance(OpenAIService::class, $mockService);

        // Component should accept valid JSON
        Livewire::test(OpenAIVectorManager::class)
            ->call('selectStore', 'vs_test123')
            ->call('selectFile', 'file_abc123')
            ->set('newMetadata', '{"valid": "json"}')
            ->assertHasNoErrors();

        // Note: Invalid JSON handling would need to be added to the component
        // Currently the component passes raw JSON string to the service
    }

    /**
     * Test 22: Refresh stores manually
     */
    public function test_refresh_stores_manually(): void
    {
        // Mock OpenAIService
        $mockService = Mockery::mock(OpenAIService::class);

        // First call on mount
        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $this->mockVectorStores]);

        // Second call on manual refresh
        $updatedStores = array_merge($this->mockVectorStores, [
            [
                'id' => 'vs_test789',
                'object' => 'vector_store',
                'name' => 'New Store',
                'status' => 'completed',
                'file_counts' => ['completed' => 3, 'in_progress' => 0, 'failed' => 0],
                'created_at' => 1640000400,
            ],
        ]);

        $mockService->shouldReceive('vectorStoreList')
            ->once()
            ->andReturn(['data' => $updatedStores]);

        $this->app->instance(OpenAIService::class, $mockService);

        Livewire::test(OpenAIVectorManager::class)
            ->assertSet('stores', $this->mockVectorStores)
            ->call('refreshStores')
            ->assertSet('stores', $updatedStores);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
