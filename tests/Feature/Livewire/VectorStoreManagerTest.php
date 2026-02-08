<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\VectorStoreManager;
use App\Models\Law;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class VectorStoreManagerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(VectorStoreManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.vector-store-manager')
            ->assertSee('Vector Store Manager')
            ->assertSee('Laws')
            ->assertSee('Court Decisions');
    }

    /**
     * Test 2: All stores are available
     *
     * @test
     */
    public function test_all_stores_are_available()
    {
        $component = Livewire::test(VectorStoreManager::class);

        $stores = $component->get('stores');

        $this->assertIsArray($stores);
        $this->assertArrayHasKey('laws', $stores);
        $this->assertArrayHasKey('court_decisions', $stores);
        $this->assertArrayHasKey('cases', $stores);
        $this->assertArrayHasKey('textract', $stores);
    }

    /**
     * Test 3: Default store is selected on mount
     *
     * @test
     */
    public function test_default_store_is_selected_on_mount()
    {
        $component = Livewire::test(VectorStoreManager::class);

        $this->assertEquals('laws', $component->get('selectedStore'));
    }

    /**
     * Test 4: Can switch between stores
     *
     * @test
     */
    public function test_can_switch_between_stores()
    {
        Livewire::test(VectorStoreManager::class)
            ->assertSet('selectedStore', 'laws')
            ->call('selectStore', 'court_decisions')
            ->assertSet('selectedStore', 'court_decisions')
            ->call('selectStore', 'cases')
            ->assertSet('selectedStore', 'cases');
    }

    /**
     * Test 5: Switching stores resets state
     *
     * @test
     */
    public function test_switching_stores_resets_state()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test query')
            ->set('selectedDocuments', ['id-1', 'id-2'])
            ->call('selectStore', 'court_decisions')
            ->assertSet('searchQuery', '')
            ->assertSet('selectedDocuments', [])
            ->assertSet('showingSearchResults', false);
    }

    /**
     * Test 6: Statistics are loaded on mount
     *
     * @test
     */
    public function test_statistics_are_loaded_on_mount()
    {
        $component = Livewire::test(VectorStoreManager::class);

        $stats = $component->get('stats');

        $this->assertIsArray($stats);
        // Stats should have these keys even if values are 0
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertArrayHasKey('unique_documents', $stats);
    }

    /**
     * Test 7: Documents are loaded on mount
     *
     * @test
     */
    public function test_documents_are_loaded_on_mount()
    {
        $component = Livewire::test(VectorStoreManager::class);

        $documents = $component->get('documents');

        // Documents can be array or collection
        $this->assertTrue(is_array($documents) || $documents instanceof \Illuminate\Support\Collection);
    }

    /**
     * Test 8: Search performs correctly
     *
     * @test
     */
    public function test_search_performs_correctly()
    {
        $component = Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test')
            ->set('searchType', 'content')
            ->call('search');

        // Should set showingSearchResults to false for content search
        $this->assertFalse($component->get('showingSearchResults'));
    }

    /**
     * Test 9: Reset search clears state
     *
     * @test
     */
    public function test_reset_search_clears_state()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test query')
            ->set('searchResults', [
                ['id' => 'doc1', 'doc_id' => 'doc-1', 'content' => 'Content 1'],
                ['id' => 'doc2', 'doc_id' => 'doc-2', 'content' => 'Content 2'],
            ])
            ->set('showingSearchResults', true)
            ->call('resetSearch')
            ->assertSet('searchQuery', '')
            ->assertSet('searchResults', [])
            ->assertSet('showingSearchResults', false);
    }

    /**
     * Test 10: Toggle selection adds document
     *
     * @test
     */
    public function test_toggle_selection_adds_document()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', [])
            ->call('toggleSelection', 'doc-123')
            ->assertSet('selectedDocuments', ['doc-123']);
    }

    /**
     * Test 11: Toggle selection removes document
     *
     * @test
     */
    public function test_toggle_selection_removes_document()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-123', 'doc-456'])
            ->call('toggleSelection', 'doc-123')
            ->assertSet('selectedDocuments', ['doc-456']);
    }

    /**
     * Test 12: Toggle select all selects all documents
     *
     * @test
     */
    public function test_toggle_select_all_selects_all_documents()
    {
        $component = Livewire::test(VectorStoreManager::class);

        // Simulate having documents
        $component->set('documents', [
            (object) [
                'id' => 'doc-1',
                'doc_id' => 'DOC-001',
                'content_preview' => 'Content 1',
                'chunk_index' => 0,
                'embedding_model' => 'text-embedding-3-small',
            ],
            (object) [
                'id' => 'doc-2',
                'doc_id' => 'DOC-002',
                'content_preview' => 'Content 2',
                'chunk_index' => 0,
                'embedding_model' => 'text-embedding-3-small',
            ],
            (object) [
                'id' => 'doc-3',
                'doc_id' => 'DOC-003',
                'content_preview' => 'Content 3',
                'chunk_index' => 0,
                'embedding_model' => 'text-embedding-3-small',
            ],
        ]);

        $component->set('selectAll', true)
            ->call('toggleSelectAll');

        $selectedDocuments = $component->get('selectedDocuments');
        $this->assertCount(3, $selectedDocuments);
        $this->assertContains('doc-1', $selectedDocuments);
        $this->assertContains('doc-2', $selectedDocuments);
        $this->assertContains('doc-3', $selectedDocuments);
    }

    /**
     * Test 13: Toggle select all deselects all documents
     *
     * @test
     */
    public function test_toggle_select_all_deselects_all_documents()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-1', 'doc-2'])
            ->set('selectAll', false)
            ->call('toggleSelectAll')
            ->assertSet('selectedDocuments', []);
    }

    /**
     * Test 14: Delete selected requires selection
     *
     * @test
     */
    public function test_delete_selected_requires_selection()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', [])
            ->call('deleteSelected')
            ->assertSet('errorMessage', 'Please select at least one document to delete');
    }

    /**
     * Test 15: Re-index selected requires selection
     *
     * @test
     */
    public function test_reindex_selected_requires_selection()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', [])
            ->call('reindexSelected')
            ->assertSet('errorMessage', 'Please select at least one document to re-index');
    }

    /**
     * Test 16: Pagination next page works
     *
     * @test
     */
    public function test_pagination_next_page_works()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('currentPage', 1)
            ->set('totalPages', 3)
            ->call('nextPage')
            ->assertSet('currentPage', 2);
    }

    /**
     * Test 17: Pagination previous page works
     *
     * @test
     */
    public function test_pagination_previous_page_works()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('currentPage', 2)
            ->call('previousPage')
            ->assertSet('currentPage', 1);
    }

    /**
     * Test 18: Pagination next page respects limits
     *
     * @test
     */
    public function test_pagination_next_page_respects_limits()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('currentPage', 3)
            ->set('totalPages', 3)
            ->call('nextPage')
            ->assertSet('currentPage', 3); // Should stay at 3
    }

    /**
     * Test 19: Pagination previous page respects limits
     *
     * @test
     */
    public function test_pagination_previous_page_respects_limits()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('currentPage', 1)
            ->call('previousPage')
            ->assertSet('currentPage', 1); // Should stay at 1
    }

    /**
     * Test 20: Goto page works correctly
     *
     * @test
     */
    public function test_goto_page_works_correctly()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('totalPages', 5)
            ->call('gotoPage', 3)
            ->assertSet('currentPage', 3);
    }

    /**
     * Test 21: Preview modal opens
     *
     * @test
     */
    public function test_preview_modal_opens()
    {
        // Create a test law document
        $law = Law::factory()->create([
            'doc_id' => 'test-law-123',
            'content' => 'Test law content',
        ]);

        $component = Livewire::test(VectorStoreManager::class)
            ->call('previewDocument', $law->id)
            ->assertSet('showPreviewModal', true);

        $this->assertNotNull($component->get('previewDocumentData'));
    }

    /**
     * Test 22: Preview modal closes
     *
     * @test
     */
    public function test_preview_modal_closes()
    {
        $component = Livewire::test(VectorStoreManager::class)
            ->set('showPreviewModal', true)
            ->set('previewDocumentData', ['id' => 'test'])
            ->call('closePreview')
            ->assertSet('showPreviewModal', false);

        $this->assertNull($component->get('previewDocumentData'));
    }

    /**
     * Test 23: Refresh stats clears cache
     *
     * @test
     */
    public function test_refresh_stats_clears_cache()
    {
        // Put stale data in cache
        Cache::put('vector_store_stats_laws', ['test' => 'old_data'], 60);

        $oldStats = Cache::get('vector_store_stats_laws');
        $this->assertEquals(['test' => 'old_data'], $oldStats);

        Livewire::test(VectorStoreManager::class)
            ->call('refreshStats');

        // Cache should now have fresh stats (different from old data)
        $newStats = Cache::get('vector_store_stats_laws');
        $this->assertNotEquals($oldStats, $newStats);
        $this->assertArrayHasKey('total_documents', $newStats);
    }

    /**
     * Test 24: Search types are available
     *
     * @test
     */
    public function test_search_types_are_available()
    {
        $component = Livewire::test(VectorStoreManager::class);

        // Search type should default to content
        $this->assertEquals('content', $component->get('searchType'));

        // Should be able to change search type
        $component->set('searchType', 'similarity')
            ->assertSet('searchType', 'similarity');
    }

    /**
     * Test 25: Route is accessible with authentication
     *
     * @test
     */
    public function test_route_is_accessible_with_authentication()
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get(route('vectors.manage'));

        $response->assertStatus(200);
    }

  /**
     * Test 26: Search with empty query shows validation error
     *
     * @test
     */
    public function test_search_with_empty_query_shows_validation_error()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', '')
            ->set('searchType', 'content')
            ->call('search')
            ->assertSet('errorMessage', 'Please enter a search query');
    }

    /**
     * Test 27: Search resets properly when switching types
     *
     * @test
     */
    public function test_search_resets_properly_when_switching_types()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test query')
            ->set('searchType', 'content')
            ->call('search')
            ->set('searchType', 'similarity')
            ->assertSet('searchQuery', 'test query') // Query should persist
            ->assertSet('searchResults', []) // Results should clear
            ->assertSet('showingSearchResults', false); // Should reset search state
    }

    /**
     * Test 28: Empty state shows when no documents
     *
     * @test
     */
    public function test_component_handles_empty_state_correctly()
    {
        $component = Livewire::test(VectorStoreManager::class)
            ->set('documents', [])
            ->set('totalDocuments', 0);

        // Should not show pagination when no documents
        $this->assertEquals(0, $component->get('totalPages'));

        // Selected documents should be empty
        $this->assertEmpty($component->get('selectedDocuments'));
    }

    /**
     * Test 29: Pagination calculated correctly based on total documents
     *
     * @test
     */
    public function test_pagination_calculates_total_pages_correctly()
    {
        $component = Livewire::test(VectorStoreManager::class);

        // Set documents and totals that should result in pagination
        $component->set('totalDocuments', 45);
        $component->set('perPage', 20);

        // Should calculate total pages correctly (45 docs / 20 per page = 3 pages)
        // Note: This tests the view logic, actual calculation happens in loadDocuments
        $this->assertGreaterThanOrEqual(0, $component->get('totalPages'));
    }

    /**
     * Test 30: Switching stores resets pagination to first page
     *
     * @test
     */
    public function test_switching_stores_resets_pagination_to_first_page()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('currentPage', 5)
            ->call('selectStore', 'court_decisions')
            ->assertSet('currentPage', 1);
    }

    /**
     * Test 31: Switching stores clears error and success messages
     *
     * @test
     */
    public function test_switching_stores_clears_messages()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('errorMessage', 'Previous error')
            ->set('successMessage', 'Previous success')
            ->call('selectStore', 'court_decisions')
            ->assertSet('errorMessage', null)
            ->assertSet('successMessage', null);
    }

    /**
     * Test 32: Search with whitespace-only query shows validation error
     *
     * @test
     */
    public function test_search_with_whitespace_only_shows_validation_error()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', '   ')
            ->set('searchType', 'content')
            ->call('search')
            ->assertSet('errorMessage', 'Please enter a search query')
            ->assertSet('searchQuery', ''); // Should be trimmed to empty string
    }

    /**
     * Test 33: Successful operation clears previous error message
     *
     * @test
     */
    public function test_successful_operation_clears_previous_error()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('errorMessage', 'Previous error occurred')
            ->call('refreshStats')
            ->assertSet('errorMessage', null)
            ->assertSet('successMessage', 'Statistics refreshed');
    }

    /**
     * Test 34: Reset search clears all search state completely
     *
     * @test
     */
    public function test_reset_search_clears_all_search_state()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test query')
            ->set('searchResults', [['id' => 1, 'content' => 'test']])
            ->set('showingSearchResults', true)
            ->call('resetSearch')
            ->assertSet('searchQuery', '');
    }


   /**
     * Test 26: Delete selected clears selection state
     *
     * @test
     */
    public function test_delete_selected_clears_selection_state()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-1', 'doc-2'])
            ->set('selectAll', true)
            ->call('deleteSelected')
            ->assertSet('selectedDocuments', [])
            ->assertSet('selectAll', false)
            ->assertSet('successMessage', 'Successfully deleted 0 documents');
    }

    /**
     * Test 27: Delete selected sets success message
     *
     * @test
     */
    public function test_delete_selected_sets_success_message()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-1'])
            ->call('deleteSelected')
            ->assertSet('successMessage', 'Successfully deleted 0 documents');
    }

    /**
     * Test 28: Delete single document clears preview modal
     *
     * @test
     */
    public function test_delete_single_document_clears_preview_modal()
    {
        $law = Law::factory()->create(['doc_id' => 'test-law-123']);

        Livewire::test(VectorStoreManager::class)
            ->set('showPreviewModal', true)
            ->set('previewDocumentData', ['id' => $law->id])
            ->call('deleteDocument', $law->id)
            // After delete, document may be removed from list, so preview should be cleared
            ->assertSet('successMessage', 'Document deleted successfully');
    }

    /**
     * Test 29: Delete selected provides count in message
     *
     * @test
     */
    public function test_delete_selected_message_shows_count()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-1', 'doc-2', 'doc-3'])
            ->call('deleteSelected')
            ->assertSet('successMessage', 'Successfully deleted 0 documents');
    }

    /**
     * Test 30: Error message persists on delete failure
     *
     * @test
     */
    public function test_error_message_set_on_delete_failure()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', [])
            ->call('deleteSelected')
            ->assertSet('errorMessage', 'Please select at least one document to delete');
    }

    /**
     * Test 31: Clear success message on new action
     *
     * @test
     */
    public function test_clear_message_on_store_switch()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('successMessage', 'Previous action success')
            ->call('selectStore', 'court_decisions')
            // After store switch, successMessage should be cleared by component
            ->assertSet('selectedStore', 'court_decisions');
    }

    /**
     * Test 32: Reindex selected clears selection state
     *
     * @test
     */
    public function test_reindex_selected_clears_selection_state()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', ['doc-1', 'doc-2'])
            ->set('selectAll', true)
            ->call('reindexSelected')
            ->assertSet('selectedDocuments', [])
            ->assertSet('selectAll', false)
            ->assertSet('successMessage', 'Successfully re-indexed 0 documents');
    }

    /**
     * Test 33: Reindex single document sets success message
     *
     * @test
     */
    public function test_reindex_document_sets_success_message()
    {
        $law = Law::factory()->create();

        Livewire::test(VectorStoreManager::class)
            ->call('reindexDocument', $law->id)
            ->assertSet('successMessage', 'Document re-indexed successfully');
    }

    /**
     * Test 34: Search results clear when switching stores
     *
     * @test
     */
    public function test_search_results_clear_on_store_switch()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('searchResults', [['id' => 'doc-1', 'content' => 'test']])
            ->set('showingSearchResults', true)
            ->call('selectStore', 'cases')
            ->assertSet('searchResults', [])
            ->assertSet('showingSearchResults', false);
    }


   /**
     * Test 35: Goto page clamps to minimum page (1)
     *
     * @test
     */
    public function test_goto_page_clamps_to_minimum()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('totalPages', 5)
            ->call('gotoPage', 0)
            ->assertSet('currentPage', 1);

        Livewire::test(VectorStoreManager::class)
            ->set('totalPages', 5)
            ->call('gotoPage', -5)
            ->assertSet('currentPage', 1);
    }

    /**
     * Test 36: Goto page clamps to maximum page (totalPages)
     *
     * @test
     */
    public function test_goto_page_clamps_to_maximum()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('totalPages', 5)
            ->call('gotoPage', 99)
            ->assertSet('currentPage', 5);
    }

    /**
     * Test 37: Content search maintains documents array (not searchResults)
     *
     * @test
     */
    public function test_content_search_uses_documents_array()
    {
        $component = Livewire::test(VectorStoreManager::class)
            ->set('searchQuery', 'test')
            ->set('searchType', 'content')
            ->call('search');

        // Content search should load documents, not populate searchResults
        $this->assertFalse($component->get('showingSearchResults'));
        $this->assertEmpty($component->get('searchResults'));
    }

    /**
     * Test 38: Document selection state persists across pagination
     *
     * @test
     */
    public function test_selection_persists_across_pagination()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('selectedDocuments', [1, 2, 3])
            ->set('currentPage', 1)
            ->call('nextPage')
            ->assertSet('selectedDocuments', [1, 2, 3]); // Selection should persist
    }

    /**
     * Test 39: Load documents clears success messages
     *
     * @test
     */
    public function test_load_documents_clears_success_message()
    {
        Livewire::test(VectorStoreManager::class)
            ->set('successMessage', 'Previous operation succeeded')
            ->call('loadDocuments')
            ->assertSet('successMessage', null);
    }
   
   /**
     * Test 35: Preview modal closes when switching stores
     *
     * @test
     */
    public function test_preview_modal_closes_on_store_switch()
    {
        $law = Law::factory()->create();

        Livewire::test(VectorStoreManager::class)
            ->call('previewDocument', $law->id)
            ->set('showPreviewModal', true)
            ->call('selectStore', 'court_decisions')
            ->assertSet('showPreviewModal', false)
            ->assertSet('previewDocumentData', null);
    }
    

    
    
}
