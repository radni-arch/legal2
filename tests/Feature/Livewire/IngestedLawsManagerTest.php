<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\IngestedLawsManager;
use App\Models\IngestedLaw;
use App\Models\Law;
use App\Models\LawUpload;
use App\Services\ZakonHrIngestService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class IngestedLawsManagerTest extends TestCase
{
    use UsesTestDatabase;

    protected $ingestService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the ZakonHrIngestService
        $this->ingestService = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $this->ingestService);
    }

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly(): void
    {
        Livewire::test(IngestedLawsManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.ingested-laws-manager')
            ->assertSet('search', '')
            ->assertSet('sortField', 'ingested_at')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('tab', 'laws');
    }

    /**
     * Test 2: Displays list of ingested laws
     *
     * @test
     */
    public function test_displays_list_of_ingested_laws(): void
    {
        // Arrange
        IngestedLaw::factory()->count(3)->create([
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
        ]);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->assertSee('Zakon o kaznenom postupku')
            ->assertSee('NN 152/08');
    }

    /**
     * Test 3: Search functionality filters laws
     *
     * @test
     */
    public function test_search_filters_ingested_laws(): void
    {
        // Arrange
        IngestedLaw::factory()->create(['title' => 'Zakon o kaznenom postupku', 'law_number' => 'NN 152/08']);
        IngestedLaw::factory()->create(['title' => 'Kazneni zakon', 'law_number' => 'NN 125/11']);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->set('search', 'kaznenom postupku')
            ->assertSee('Zakon o kaznenom postupku')
            ->assertDontSee('Kazneni zakon');
    }

    /**
     * Test 4: Pagination works correctly
     *
     * @test
     */
    public function test_pagination_works(): void
    {
        // Arrange: Create more than 10 laws (default per page)
        IngestedLaw::factory()->count(15)->create();

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->assertStatus(200)
            ->assertSee('page'); // Pagination links should be present
    }

    /**
     * Test 5: Delete law removes it from database
     *
     * @test
     */
    public function test_delete_law_confirmation(): void
    {
        // Arrange
        $law = IngestedLaw::factory()->create(['title' => 'Test Law to Delete']);

        // Assert exists before delete
        $this->assertDatabaseHas('ingested_laws', ['id' => $law->id]);

        // Act: Delete the law
        Livewire::test(IngestedLawsManager::class)
            ->call('deleteIngested', $law->id);

        // Assert: Law is removed from database
        $this->assertDatabaseMissing('ingested_laws', ['id' => $law->id]);
    }

    /**
     * Test 6: View law details modal opens
     *
     * @test
     */
    public function test_view_law_details_modal(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::factory()->create();
        $law = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Članak 1',
            'content' => 'Test law content',
        ]);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->call('viewLaw', $law->id)
            ->assertSet('showLawViewModal', true)
            ->assertSet('viewingLaw.title', 'Članak 1')
            ->assertSet('viewingLaw.content', 'Test law content');
    }

    /**
     * Test 7: Edit law modal opens with data
     *
     * @test
     */
    public function test_edit_law_modal_opens(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::factory()->create();
        $law = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->call('editLaw', $law->id)
            ->assertSet('showLawModal', true)
            ->assertSet('editingLaw.title', 'Original Title')
            ->assertSet('editingLaw.content', 'Original Content');
    }

    /**
     * Test 8: Create new ingested law modal opens
     *
     * @test
     */
    public function test_create_new_law_modal_opens(): void
    {
        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->assertSet('showIngestedModal', true)
            ->assertSet('editingIngested.id', null)
            ->assertSet('editingIngested.doc_id', '')
            ->assertSet('editingIngested.title', '');
    }

    /**
     * Test 9: Save edited ingested law
     *
     * @test
     */
    public function test_save_edited_ingested_law(): void
    {
        // Arrange
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'original-doc-id',
            'title' => 'Original Title',
        ]);

        // Act
        Livewire::test(IngestedLawsManager::class)
            ->call('editIngested', $law->id)
            ->set('editingIngested.title', 'Updated Title')
            ->call('saveIngested');

        // Assert
        $this->assertDatabaseHas('ingested_laws', [
            'id' => $law->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test 10: Sort by different fields
     *
     * @test
     */
    public function test_sort_by_different_fields(): void
    {
        // Arrange
        IngestedLaw::factory()->create(['title' => 'A Law', 'law_number' => 'NN 100/20']);
        IngestedLaw::factory()->create(['title' => 'Z Law', 'law_number' => 'NN 50/21']);

        // Act & Assert: Sort by title ascending
        Livewire::test(IngestedLawsManager::class)
            ->call('sortBy', 'title')
            ->assertSet('sortField', 'title')
            ->assertSet('sortDirection', 'asc');
    }

    /**
     * Test 11: Tab switching between laws and uploads
     *
     * @test
     */
    public function test_tab_switching_works(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::factory()->create();

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingestedLaw->id)
            ->assertSet('tab', 'laws')
            ->set('tab', 'uploads')
            ->assertSet('tab', 'uploads');
    }

    /**
     * Test 12: Scraper modal opens correctly
     *
     * @test
     */
    public function test_scraper_modal_opens(): void
    {
        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->call('openScraper')
            ->assertSet('showScraperModal', true)
            ->assertSet('scrapedLaws', [])
            ->assertSet('selectedLawsToImport', []);
    }

    /**
     * Test 13: Error handling for validation
     *
     * @test
     */
    public function test_error_handling_displays(): void
    {
        // Act & Assert: Try to save with empty required field
        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', '') // Required field
            ->call('saveIngested')
            ->assertHasErrors(['editingIngested.doc_id']);
    }

    /**
     * Test 14: Last ingested timestamp is displayed
     *
     * @test
     */
    public function test_last_ingested_timestamp_display(): void
    {
        // Arrange
        $law = IngestedLaw::factory()->create([
            'title' => 'Recent Law',
            'ingested_at' => now()->subDays(2),
        ]);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->assertSee('Recent Law');

        // The ingested_at timestamp should be accessible
        $this->assertNotNull($law->ingested_at);
    }

    /**
     * Test 15: Select ingested law and view children
     *
     * @test
     */
    public function test_select_ingested_law_and_view_children(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::factory()->create(['title' => 'Parent Law']);
        $childLaw = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Child Article 1',
            'content' => 'Article content',
        ]);

        // Act & Assert
        Livewire::test(IngestedLawsManager::class)
            ->call('selectIngested', $ingestedLaw->id)
            ->assertSet('selectedIngestedId', $ingestedLaw->id)
            ->assertSet('tab', 'laws')
            ->assertSee('Child Article 1');
    }

    /**
     * Test 21: editIngested opens modal with law data
     *
     * @test
     */
    public function test_edit_ingested_opens_modal_with_law_data()
    {
        $law = IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'doc_id' => 'zkp-2023',
            'law_number' => 'NN 152/08',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('editIngested', $law->id)
            ->assertSet('showIngestedModal', true)
            ->assertSet('editingIngested.id', $law->id)
            ->assertSet('editingIngested.title', 'Zakon o kaznenom postupku')
            ->assertSet('editingIngested.doc_id', 'zkp-2023');
    }

    /**
     * Test 22: saveIngested creates new ingested law
     *
     * @test
     */
    public function test_save_ingested_creates_new_law()
    {
        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', 'kz-2023')
            ->set('editingIngested.title', 'Kazneni zakon')
            ->set('editingIngested.law_number', 'NN 125/11')
            ->call('saveIngested');

        $this->assertDatabaseHas('ingested_laws', [
            'doc_id' => 'kz-2023',
            'title' => 'Kazneni zakon',
            'law_number' => 'NN 125/11',
        ]);
    }

    /**
     * Test 23: saveIngested updates existing law
     *
     * @test
     */
    public function test_save_ingested_updates_existing_law()
    {
        $law = IngestedLaw::factory()->create([
            'title' => 'Original Title',
            'doc_id' => 'test-123',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('editIngested', $law->id)
            ->set('editingIngested.title', 'Updated Title')
            ->call('saveIngested');

        $this->assertDatabaseHas('ingested_laws', [
            'id' => $law->id,
            'title' => 'Updated Title',
            'doc_id' => 'test-123',
        ]);
    }

    /**
     * Test 24: viewLaw opens modal with law details
     *
     * @test
     */
    public function test_view_law_opens_modal_with_details()
    {
        $ingested = IngestedLaw::factory()->create();
        $law = Law::factory()->create([
            'ingested_law_id' => $ingested->id,
            'title' => 'Article 123',
            'content' => 'Test law content',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('viewLaw', $law->id)
            ->assertSet('showLawViewModal', true)
            ->assertSet('viewingLaw.id', $law->id)
            ->assertSet('viewingLaw.title', 'Article 123')
            ->assertSet('viewingLaw.content', 'Test law content');
    }

    /**
     * Test 25: deleteLaw removes law chunk from database
     *
     * @test
     */
    public function test_delete_law_removes_chunk_from_database()
    {
        $ingested = IngestedLaw::factory()->create();
        $law = Law::factory()->create([
            'ingested_law_id' => $ingested->id,
        ]);

        $this->assertDatabaseHas('laws', ['id' => $law->id]);

        Livewire::test(IngestedLawsManager::class)
            ->call('deleteLaw', $law->id);

        $this->assertDatabaseMissing('laws', ['id' => $law->id]);
    }

    /**
     * Test 26: openScraper initializes scraper modal
     *
     * @test
     */
    public function test_open_scraper_initializes_modal()
    {
        Livewire::test(IngestedLawsManager::class)
            ->call('openScraper')
            ->assertSet('showScraperModal', true)
            ->assertSet('scraperSearchFilter', '')
            ->assertSet('selectedLawsToImport', [])
            ->assertSet('scrapedLaws', []);
    }

    /**
     * Test 27: toggleLawSelection adds and removes laws
     *
     * @test
     */
    public function test_toggle_law_selection_adds_and_removes()
    {
        Livewire::test(IngestedLawsManager::class)
            ->call('toggleLawSelection', 'https://example.com/law1')
            ->assertSet('selectedLawsToImport', ['https://example.com/law1'])
            ->call('toggleLawSelection', 'https://example.com/law2')
            ->assertCount('selectedLawsToImport', 2)
            ->call('toggleLawSelection', 'https://example.com/law1')
            ->assertCount('selectedLawsToImport', 1)
            ->assertSet('selectedLawsToImport', ['https://example.com/law2']);
    }

    /**
     * Test 28: selectAllFilteredLaws selects all scraped laws
     *
     * @test
     */
    public function test_select_all_filtered_laws()
    {
        $component = Livewire::test(IngestedLawsManager::class);
        $component->set('scrapedLaws', [
            ['title' => 'Law 1', 'url' => 'https://example.com/law1', 'law_number' => 'NN 1/2023', 'slug' => 'law-1'],
            ['title' => 'Law 2', 'url' => 'https://example.com/law2', 'law_number' => 'NN 2/2023', 'slug' => 'law-2'],
            ['title' => 'Law 3', 'url' => 'https://example.com/law3', 'law_number' => 'NN 3/2023', 'slug' => 'law-3'],
        ]);

        $component->call('selectAllFilteredLaws')
            ->assertCount('selectedLawsToImport', 3);
    }

    /**
     * Test 29: deselectAllLaws clears selection
     *
     * @test
     */
    public function test_deselect_all_laws_clears_selection()
    {
        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', [
                'https://example.com/law1',
                'https://example.com/law2',
            ])
            ->call('deselectAllLaws')
            ->assertSet('selectedLawsToImport', []);
    }

    /**
     * Test 30: getFilteredScrapedLaws filters by search term
     *
     * @test
     */
    public function test_get_filtered_scraped_laws_filters_correctly()
    {
        $component = Livewire::test(IngestedLawsManager::class);
        $component->set('scrapedLaws', [
            ['title' => 'Zakon o kaznenom postupku', 'url' => 'url1', 'law_number' => 'NN 152/08'],
            ['title' => 'Kazneni zakon', 'url' => 'url2', 'law_number' => 'NN 125/11'],
            ['title' => 'Ustav Republike Hrvatske', 'url' => 'url3', 'law_number' => 'NN 56/90'],
        ]);

        // Search for "zakon" which appears in first two titles
        $component->set('scraperSearchFilter', 'zakon');
        $filtered = $component->instance()->getFilteredScrapedLaws();

        $this->assertCount(2, $filtered);
        // Get the filtered values (array_filter preserves keys, so re-index)
        $filteredValues = array_values($filtered);
        $this->assertStringContainsString('zakon', strtolower($filteredValues[0]['title']));
    }

    /**
     * Test 31: importSelectedLaws dispatches error when no laws selected
     *
     * @test
     */
    public function test_import_selected_laws_errors_when_empty()
    {
        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', [])
            ->call('importSelectedLaws')
            ->assertDispatched('import-error');
    }

    /**
     * Test 32: importSelectedLaws processes selected laws
     *
     * @test
     */
    public function test_import_selected_laws_processes_urls()
    {
        $this->ingestService->shouldReceive('ingestUrls')
            ->once()
            ->with(['https://example.com/law1'])
            ->andReturn([
                'urls_processed' => 1,
                'articles_seen' => 10,
                'inserted' => 10,
            ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', ['https://example.com/law1'])
            ->call('importSelectedLaws')
            ->assertSet('isImporting', false)
            ->assertDispatched('import-complete');
    }

    /**
     * Test 33: saveIngested validates required doc_id
     *
     * @test
     */
    public function test_save_ingested_validates_required_doc_id()
    {
        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', '')
            ->set('editingIngested.title', 'Test Law')
            ->call('saveIngested')
            ->assertHasErrors(['editingIngested.doc_id' => 'required']);
    }

    /**
     * Test 34: saveIngested validates unique doc_id
     *
     * @test
     */
    public function test_save_ingested_validates_unique_doc_id()
    {
        IngestedLaw::factory()->create(['doc_id' => 'duplicate-123']);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', 'duplicate-123')
            ->set('editingIngested.title', 'Test Law')
            ->call('saveIngested')
            ->assertHasErrors(['editingIngested.doc_id' => 'unique']);
    }

    /**
     * Test 35: createLaw initializes form with parent data
     *
     * @test
     */
    public function test_create_law_initializes_with_parent_data()
    {
        $ingested = IngestedLaw::factory()->create([
            'doc_id' => 'zkp-2023',
            'jurisdiction' => 'Republic of Croatia',
            'country' => 'HR',
            'language' => 'hr',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('createLaw')
            ->assertSet('showLawModal', true)
            ->assertSet('editingLaw.doc_id', 'zkp-2023')
            ->assertSet('editingLaw.jurisdiction', 'Republic of Croatia')
            ->assertSet('editingLaw.country', 'HR')
            ->assertSet('editingLaw.language', 'hr')
            ->assertSet('editingLaw.chunk_index', 0);
    }

    /**
     * Test 36: saveLaw validates required fields
     *
     * @test
     */
    public function test_save_law_validates_required_fields()
    {
        $ingested = IngestedLaw::factory()->create();

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('createLaw')
            ->set('editingLaw.doc_id', '')
            ->set('editingLaw.content', '')
            ->call('saveLaw')
            ->assertHasErrors([
                'editingLaw.doc_id' => 'required',
                'editingLaw.content' => 'required',
            ]);
    }

    /**
     * Test 37: Component displays child laws for selected ingested law
     *
     * @test
     */
    public function test_displays_child_laws_for_selected()
    {
        $ingested = IngestedLaw::factory()->create(['title' => 'Parent Law']);
        Law::factory()->count(3)->create([
            'ingested_law_id' => $ingested->id,
            'title' => 'Child Article',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->assertSee('Child Article');
    }

    /**
     * Test 38: updatingSearch resets pagination
     *
     * @test
     */
    public function test_updating_search_resets_pagination()
    {
        IngestedLaw::factory()->count(25)->create();

        $component = Livewire::test(IngestedLawsManager::class)
            ->call('gotoPage', 2, 'page');

        // Setting search should reset to page 1 (via updatingSearch hook)
        // The updatingSearch method calls resetPage()
        $component->set('search', 'test')
            ->assertSet('search', 'test')
            ->assertStatus(200);
    }

    /**
     * Test 39: deleteUpload removes upload from database
     *
     * @test
     */
    public function test_delete_upload_removes_from_database()
    {
        $ingested = IngestedLaw::factory()->create();
        $upload = LawUpload::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => $ingested->doc_id,
            'disk' => 'local',
            'local_path' => '/test/path/file.pdf',
            'status' => 'stored',
        ]);

        $this->assertDatabaseHas('law_uploads', ['id' => $upload->id]);

        Livewire::test(IngestedLawsManager::class)
            ->call('deleteUpload', $upload->id);

        $this->assertDatabaseMissing('law_uploads', ['id' => $upload->id]);
    }

    /**
     * Test 40: Component shows empty state when no laws
     *
     * @test
     */
    public function test_shows_empty_state_when_no_laws()
    {
        // Ensure database is empty for this test
        IngestedLaw::query()->delete();

        Livewire::test(IngestedLawsManager::class)
            ->assertStatus(200)
            ->assertDontSee('Test Law Title'); // Should not see any law titles
    }

    /**
     * Test 41: downloadLaw returns a file download response
     *
     * @test
     */
    public function test_download_law_returns_file_download(): void
    {
        $ingested = IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'Republic of Croatia',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => 'https://zakon.hr/z/test',
        ]);

        Law::factory()->create([
            'ingested_law_id' => $ingested->id,
            'chapter' => 'Glava I',
            'section' => 'Članak 1.',
            'content' => 'Test law content for download',
            'chunk_index' => 0,
        ]);

        Law::factory()->create([
            'ingested_law_id' => $ingested->id,
            'chapter' => 'Glava II',
            'section' => 'Članak 2.',
            'content' => 'Second chunk content',
            'chunk_index' => 1,
        ]);

        $component = Livewire::test(IngestedLawsManager::class)
            ->call('downloadLaw', $ingested->id)
            ->assertFileDownloaded('zakon-o-kaznenom-postupku.txt');
    }

    /**
     * Test 42: viewIngestedDetails selects the ingested law
     *
     * @test
     */
    public function test_view_ingested_details_selects_law(): void
    {
        $ingested = IngestedLaw::factory()->create(['title' => 'Test Law']);

        Livewire::test(IngestedLawsManager::class)
            ->call('viewIngestedDetails', $ingested->id)
            ->assertSet('selectedIngestedId', $ingested->id)
            ->assertSet('tab', 'laws');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
