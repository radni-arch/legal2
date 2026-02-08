<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * OpenAI Vector Manager E2E Test Suite
 *
 * Comprehensive browser tests for the OpenAI Vector Manager component.
 * Tests all core functionality including:
 * - Displaying available vector stores
 * - Selecting and listing files within stores
 * - Viewing file metadata and attributes
 * - Editing and saving metadata
 * - Error handling and validation
 * - Empty state messages
 *
 * The component manages vector embeddings and vector store operations
 * used for RAG (Retrieval Augmented Generation) and semantic search.
 */
class OpenAIVectorManagerTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external APIs for offline testing
        $this->mockAllExternalApis();

        // Create test user with unique email to avoid conflicts
        $this->user = User::factory()->create([
            'email' => 'vector-test-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test user
        if (isset($this->user)) {
            $this->user->delete();
        }

        parent::tearDown();
    }

    /**
     * Test: Component loads successfully
     */
    public function test_component_loads_successfully(): void
    {
        $this->mockVectorStoresEmpty();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@vector-manager-container')
                ->assertPresent('@header-section')
                ->assertPresent('@page-title')
                ->assertSee('OpenAI Vector Stores Manager');
        });
    }

    /**
     * Test: Empty state displays when no vector stores exist
     */
    public function test_empty_state_displays_when_no_stores_exist(): void
    {
        $this->mockVectorStoresEmpty();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@empty-stores-message')
                ->assertSee('No vector stores available');
        });
    }

    /**
     * Test: Displays list of vector stores correctly
     */
    public function test_displays_vector_stores_list(): void
    {
        $stores = $this->createSampleStores();
        $this->mockVectorStoresList($stores);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@stores-list')
                ->assertPresent('@store-item')
                ->assertPresent('@store-button')
                ->assertSee('Laws Store')
                ->assertSee('Cases Store')
                ->assertPresent('@stores-count')
                ->assertSee('2 store(s)');
        });
    }

    /**
     * Test: Store selection loads files in that store
     */
    public function test_selecting_store_loads_files(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@store-button-0')
                // Click first store
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Files panel should now show files
                ->assertPresent('@files-panel')
                ->assertPresent('@files-list')
                ->assertPresent('@file-item')
                ->assertSee('law_document_1.pdf')
                ->assertPresent('@files-count')
                ->assertSee('2 file(s)');
        });
    }

    /**
     * Test: Shows message when no files in selected store
     */
    public function test_shows_message_when_store_has_no_files(): void
    {
        $stores = $this->createSampleStores();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => []], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@empty-files-message')
                ->assertSee('No files in this store');
        });
    }

    /**
     * Test: File selection loads metadata and attributes
     */
    public function test_selecting_file_loads_metadata(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();
        $fileDetails = $this->createFileDetails();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
            'api.openai.com/v1/vector_stores/*/files/*' => Http::response($fileDetails, 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@file-button-0')
                // Click first file
                ->click('@file-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Metadata panel should show
                ->assertPresent('@metadata-panel')
                ->assertPresent('@file-info-section')
                ->assertPresent('@metadata-display')
                ->assertPresent('@attributes-display');
        });
    }

    /**
     * Test: Shows select file message when no file is selected
     */
    public function test_shows_select_file_message_when_none_selected(): void
    {
        $stores = $this->createSampleStores();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => []], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@metadata-panel')
                ->assertPresent('@select-file-message')
                ->assertSee('Select a file to view and edit metadata');
        });
    }

    /**
     * Test: Shows select store message in files panel
     */
    public function test_shows_select_store_message_in_files_panel(): void
    {
        $this->mockVectorStoresEmpty();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@files-panel')
                ->assertPresent('@select-store-message')
                ->assertSee('Select a store to view files');
        });
    }

    /**
     * Test: Metadata form is displayed when file is selected
     */
    public function test_metadata_form_displays_when_file_selected(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();
        $fileDetails = $this->createFileDetails();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
            'api.openai.com/v1/vector_stores/*/files/*' => Http::response($fileDetails, 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->click('@file-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Check form elements
                ->assertPresent('@metadata-form')
                ->assertPresent('@metadata-input-section')
                ->assertPresent('@metadata-input')
                ->assertPresent('@attributes-input-section')
                ->assertPresent('@attributes-input')
                ->assertPresent('@save-metadata-button');
        });
    }

    /**
     * Test: Can save metadata with valid JSON
     */
    public function test_can_save_metadata_with_valid_json(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();
        $fileDetails = $this->createFileDetails();
        $updatedFileDetails = $fileDetails;
        $updatedFileDetails['metadata'] = ['key' => 'value'];

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
            'api.openai.com/v1/vector_stores/*/files/*' => Http::sequence()
                ->push($fileDetails, 200)
                ->push($updatedFileDetails, 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->click('@file-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Fill metadata input
                ->type('@metadata-input', '{"key": "value"}')
                ->pause(500)
                // Submit form
                ->click('@save-metadata-button')
                ->waitForLivewire()
                ->pause(500)
                // Should not show error
                ->assertMissing('@error-alert');
        });
    }

    /**
     * Test: Error alert displays when API fails
     */
    public function test_error_alert_displays_when_api_fails(): void
    {
        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->pause(1000)
                // Should show error alert
                ->assertPresent('@error-alert')
                ->assertPresent('@error-message');
        });
    }

    /**
     * Test: Store selection changes highlight color
     */
    public function test_store_selection_changes_highlight(): void
    {
        $stores = $this->createSampleStores();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => []], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                // Click first store
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                // First button should be highlighted (blue)
                ->assertPresent('@store-button-0')
                // Click second store
                ->click('@store-button-1')
                ->waitForLivewire()
                ->pause(500)
                // Second button should be highlighted now
                ->assertPresent('@store-button-1');
        });
    }

    /**
     * Test: File selection changes highlight color
     */
    public function test_file_selection_changes_highlight(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();
        $fileDetails = $this->createFileDetails();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
            'api.openai.com/v1/vector_stores/*/files/*' => Http::response($fileDetails, 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Click first file
                ->click('@file-button-0')
                ->waitForLivewire()
                ->pause(500)
                // First file should be highlighted (green)
                ->assertPresent('@file-button-0')
                // Click second file
                ->click('@file-button-1')
                ->waitForLivewire()
                ->pause(500)
                // Second file should be highlighted now
                ->assertPresent('@file-button-1');
        });
    }

    /**
     * Test: File details display in right panel
     */
    public function test_file_details_display_correctly(): void
    {
        $stores = $this->createSampleStores();
        $files = $this->createSampleFiles();
        $fileDetails = $this->createFileDetails();

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
            'api.openai.com/v1/vector_stores/*/files/*' => Http::response($fileDetails, 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->click('@file-button-0')
                ->waitForLivewire()
                ->pause(500)
                // Check file information is displayed
                ->assertPresent('@file-info-section')
                ->assertPresent('@metadata-display-label')
                ->assertPresent('@attributes-display-label')
                ->assertPresent('@metadata-display')
                ->assertPresent('@attributes-display');
        });
    }

    /**
     * Test: Store list shows file counts when available
     */
    public function test_store_list_shows_file_counts(): void
    {
        $stores = [
            ['id' => 'vs-123', 'name' => 'Laws Store', 'file_count' => 5],
            ['id' => 'vs-456', 'name' => 'Cases Store', 'file_count' => 3],
        ];

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@store-file-count-0')
                ->assertSee('Files: 5')
                ->assertPresent('@store-file-count-1')
                ->assertSee('Files: 3');
        });
    }

    /**
     * Test: File list shows file sizes when available
     */
    public function test_file_list_shows_file_sizes(): void
    {
        $stores = $this->createSampleStores();
        $files = [
            ['id' => 'file-001', 'filename' => 'law_document_1.pdf', 'size' => 102400], // 100 KB
            ['id' => 'file-002', 'filename' => 'case_brief.pdf', 'size' => 51200], // 50 KB
        ];

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => $files], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertPresent('@file-size-0')
                ->assertSee('100.00 KB')
                ->assertPresent('@file-size-1')
                ->assertSee('50.00 KB');
        });
    }

    /**
     * Test: Component renders page subtitle
     */
    public function test_page_subtitle_renders(): void
    {
        $this->mockVectorStoresEmpty();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                ->assertPresent('@page-subtitle')
                ->assertSee('Manage vector embeddings and vector store operations');
        });
    }

    /**
     * Test: Multiple stores can be selected sequentially
     */
    public function test_multiple_stores_can_be_selected_sequentially(): void
    {
        $stores = $this->createSampleStores();
        $filesStore1 = [
            ['id' => 'file-001', 'filename' => 'store1_doc.pdf'],
        ];
        $filesStore2 = [
            ['id' => 'file-002', 'filename' => 'store2_doc.pdf'],
        ];

        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
        ]);

        // Mock different responses for different vector stores
        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/vs-123/files' => Http::response(['data' => $filesStore1], 200),
            'api.openai.com/v1/vector_stores/vs-456/files' => Http::response(['data' => $filesStore2], 200),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser, $this->user);

            $browser->visit('/test-openai-vector-manager')
                ->waitForLivewire()
                // Select first store
                ->click('@store-button-0')
                ->waitForLivewire()
                ->pause(500)
                ->assertSee('store1_doc.pdf')
                // Select second store
                ->click('@store-button-1')
                ->waitForLivewire()
                ->pause(500)
                ->assertSee('store2_doc.pdf');
        });
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Mock empty vector stores response
     */
    protected function mockVectorStoresEmpty(): void
    {
        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => []], 200),
        ]);
    }

    /**
     * Mock vector stores list response
     */
    protected function mockVectorStoresList(array $stores): void
    {
        Http::fake([
            'api.openai.com/v1/vector_stores' => Http::response(['data' => $stores], 200),
            'api.openai.com/v1/vector_stores/*/files' => Http::response(['data' => []], 200),
        ]);
    }

    /**
     * Create sample vector stores for testing
     */
    protected function createSampleStores(): array
    {
        return [
            [
                'id' => 'vs-123',
                'name' => 'Laws Store',
                'created_at' => now()->timestamp,
                'file_count' => 2,
                'usage_bytes' => 1024000,
            ],
            [
                'id' => 'vs-456',
                'name' => 'Cases Store',
                'created_at' => now()->subDay()->timestamp,
                'file_count' => 2,
                'usage_bytes' => 2048000,
            ],
        ];
    }

    /**
     * Create sample files for testing
     */
    protected function createSampleFiles(): array
    {
        return [
            [
                'id' => 'file-001',
                'filename' => 'law_document_1.pdf',
                'created_at' => now()->timestamp,
                'size' => 102400,
                'status' => 'completed',
            ],
            [
                'id' => 'file-002',
                'filename' => 'law_document_2.pdf',
                'created_at' => now()->subHours(2)->timestamp,
                'size' => 204800,
                'status' => 'completed',
            ],
        ];
    }

    /**
     * Create sample file details with metadata and attributes
     */
    protected function createFileDetails(): array
    {
        return [
            'id' => 'file-001',
            'filename' => 'law_document_1.pdf',
            'created_at' => now()->timestamp,
            'size' => 102400,
            'status' => 'completed',
            'metadata' => [
                'source' => 'croatian_laws',
                'category' => 'criminal_procedure',
            ],
            'attributes' => [
                'law_code' => 'ZKP',
                'year' => 2022,
            ],
        ];
    }
}
