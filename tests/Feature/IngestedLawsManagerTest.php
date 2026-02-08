<?php

namespace Tests\Feature;

use App\Http\Livewire\IngestedLawsManager;
use App\Models\IngestedLaw;
use App\Models\Law;
use App\Models\LawUpload;
use App\Services\ZakonHrIngestService;
use App\Services\ZakonHrScraper;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive tests for IngestedLawsManager Livewire component
 *
 * Tests cover:
 * - Component rendering and initialization
 * - IngestedLaw CRUD operations
 * - Law (child) CRUD operations
 * - LawUpload CRUD operations
 * - Pagination and search functionality
 * - Sorting functionality
 * - Tab switching
 * - Modal interactions
 * - Validation rules
 * - Scraping functionality
 * - Import functionality
 * - Selection and filtering
 */
class IngestedLawsManagerTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test component renders successfully
     *
     * @test
     */
    public function it_renders_successfully(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.ingested-laws-manager');
    }

    /**
     * Test component initializes with default properties
     *
     * @test
     */
    public function it_initializes_with_default_properties(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->assertSet('search', '')
            ->assertSet('sortField', 'ingested_at')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('selectedIngestedId', null)
            ->assertSet('tab', 'laws')
            ->assertSet('showIngestedModal', false)
            ->assertSet('showLawModal', false)
            ->assertSet('showUploadModal', false);
    }

    /**
     * Test displays paginated ingested laws
     *
     * @test
     */
    public function it_displays_paginated_ingested_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        // Create 15 ingested laws
        for ($i = 0; $i < 15; $i++) {
            IngestedLaw::create([
                'id' => (string) Str::ulid(),
                'doc_id' => "LAW-{$i}",
                'title' => "Test Law {$i}",
                'law_number' => "123/{$i}",
                'ingested_at' => now()->subDays($i),
            ]);
        }

        Livewire::test(IngestedLawsManager::class)
            ->assertViewHas('ingested', function ($ingested) {
                return $ingested->total() === 15 && $ingested->perPage() === 10;
            });
    }

    /**
     * Test search filters ingested laws
     *
     * @test
     */
    public function it_filters_ingested_laws_by_search(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'LAW-MATCH',
            'title' => 'Matching Law',
            'law_number' => '123/2023',
            'ingested_at' => now(),
        ]);

        IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'LAW-OTHER',
            'title' => 'Other Law',
            'law_number' => '456/2023',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('search', 'MATCH')
            ->assertViewHas('ingested', function ($ingested) {
                return $ingested->total() === 1
                    && $ingested->first()->doc_id === 'LAW-MATCH';
            });
    }

    /**
     * Test sorting by different fields
     *
     * @test
     */
    public function it_sorts_ingested_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'LAW-B',
            'title' => 'B Law',
            'ingested_at' => now()->subDays(1),
        ]);

        IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'LAW-A',
            'title' => 'A Law',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('sortBy', 'doc_id')
            ->assertSet('sortField', 'doc_id')
            ->assertSet('sortDirection', 'asc')
            ->assertViewHas('ingested', function ($ingested) {
                return $ingested->first()->doc_id === 'LAW-A';
            });
    }

    /**
     * Test sorting toggles direction
     *
     * @test
     */
    public function it_toggles_sort_direction(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('sortBy', 'title')
            ->assertSet('sortField', 'title')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'title')
            ->assertSet('sortDirection', 'desc');
    }

    /**
     * Test opening create ingested modal
     *
     * @test
     */
    public function it_opens_create_ingested_modal(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->assertSet('showIngestedModal', true)
            ->assertSet('editingIngested.id', null)
            ->assertSet('editingIngested.doc_id', '');
    }

    /**
     * Test creating new ingested law
     *
     * @test
     */
    public function it_creates_new_ingested_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', 'NEW-LAW-123')
            ->set('editingIngested.title', 'New Law')
            ->set('editingIngested.law_number', '789/2023')
            ->set('editingIngested.jurisdiction', 'HR')
            ->call('saveIngested')
            ->assertSet('showIngestedModal', false);

        $this->assertDatabaseHas('ingested_laws', [
            'doc_id' => 'NEW-LAW-123',
            'title' => 'New Law',
            'law_number' => '789/2023',
        ]);
    }

    /**
     * Test editing existing ingested law
     *
     * @test
     */
    public function it_edits_existing_ingested_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $law = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'EDIT-LAW',
            'title' => 'Original Title',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('editIngested', $law->id)
            ->assertSet('showIngestedModal', true)
            ->assertSet('editingIngested.id', $law->id)
            ->assertSet('editingIngested.doc_id', 'EDIT-LAW')
            ->set('editingIngested.title', 'Updated Title')
            ->call('saveIngested')
            ->assertSet('showIngestedModal', false);

        $this->assertDatabaseHas('ingested_laws', [
            'id' => $law->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test deleting ingested law
     *
     * @test
     */
    public function it_deletes_ingested_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $law = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'DELETE-LAW',
            'title' => 'To Delete',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('deleteIngested', $law->id);

        $this->assertDatabaseMissing('ingested_laws', [
            'id' => $law->id,
        ]);
    }

    /**
     * Test selecting ingested law
     *
     * @test
     */
    public function it_selects_ingested_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $law = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'SELECT-LAW',
            'title' => 'Selectable',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('selectIngested', $law->id)
            ->assertSet('selectedIngestedId', $law->id)
            ->assertSet('tab', 'laws');
    }

    /**
     * Test validation for ingested law creation
     *
     * @test
     */
    public function it_validates_ingested_law_doc_id_is_required(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', '')
            ->set('editingIngested.title', 'Test')
            ->call('saveIngested')
            ->assertHasErrors(['editingIngested.doc_id' => 'required']);
    }

    /**
     * Test validation for unique doc_id
     *
     * @test
     */
    public function it_validates_ingested_law_doc_id_is_unique(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'EXISTING-LAW',
            'title' => 'Existing',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', 'EXISTING-LAW')
            ->set('editingIngested.title', 'Duplicate')
            ->call('saveIngested')
            ->assertHasErrors(['editingIngested.doc_id' => 'unique']);
    }

    /**
     * Test creating child Law
     *
     * @test
     */
    public function it_creates_child_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'jurisdiction' => 'HR',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('createLaw')
            ->assertSet('showLawModal', true)
            ->assertSet('editingLaw.doc_id', 'PARENT-LAW')
            ->assertSet('editingLaw.jurisdiction', 'HR')
            ->set('editingLaw.content', 'Law content here')
            ->set('editingLaw.chunk_index', 0)
            ->call('saveLaw')
            ->assertSet('showLawModal', false);

        $this->assertDatabaseHas('laws', [
            'doc_id' => 'PARENT-LAW',
            'ingested_law_id' => $ingested->id,
        ]);
    }

    /**
     * Test editing child Law
     *
     * @test
     */
    public function it_edits_child_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        $law = Law::create([
            'id' => (string) Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => 'PARENT-LAW',
            'title' => 'Original Law Content',
            'chunk_index' => 0,
            'content' => 'Original content',
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Original content'),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('editLaw', $law->id)
            ->assertSet('showLawModal', true)
            ->assertSet('editingLaw.id', $law->id)
            ->set('editingLaw.content', 'Updated content')
            ->call('saveLaw')
            ->assertSet('showLawModal', false);

        $this->assertDatabaseHas('laws', [
            'id' => $law->id,
            'content' => 'Updated content',
        ]);
    }

    /**
     * Test deleting child Law
     *
     * @test
     */
    public function it_deletes_child_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        $law = Law::create([
            'id' => (string) Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => 'PARENT-LAW',
            'chunk_index' => 0,
            'content' => 'Content to delete',
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Content'),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('deleteLaw', $law->id);

        $this->assertDatabaseMissing('laws', [
            'id' => $law->id,
        ]);
    }

    /**
     * Test viewing child Law
     *
     * @test
     */
    public function it_views_child_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        $law = Law::create([
            'id' => (string) Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => 'PARENT-LAW',
            'title' => 'View Law',
            'chunk_index' => 0,
            'content' => 'Content to view',
            'embedding_provider' => 'manual',
            'embedding_model' => 'none',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Content'),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('viewLaw', $law->id)
            ->assertSet('showLawViewModal', true)
            ->assertSet('viewingLaw.id', $law->id)
            ->assertSet('viewingLaw.content', 'Content to view');
    }

    /**
     * Test Law validation - content required
     *
     * @test
     */
    public function it_validates_law_content_is_required(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('createLaw')
            ->set('editingLaw.doc_id', 'TEST')
            ->set('editingLaw.content', '')
            ->set('editingLaw.chunk_index', 0)
            ->call('saveLaw')
            ->assertHasErrors(['editingLaw.content' => 'required']);
    }

    /**
     * Test creating LawUpload
     *
     * @test
     */
    public function it_creates_law_upload(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('createUpload')
            ->assertSet('showUploadModal', true)
            ->assertSet('editingUpload.doc_id', 'PARENT-LAW')
            ->set('editingUpload.disk', 'local')
            ->set('editingUpload.local_path', '/path/to/file.pdf')
            ->set('editingUpload.status', 'stored')
            ->call('saveUpload')
            ->assertSet('showUploadModal', false);

        $this->assertDatabaseHas('law_uploads', [
            'doc_id' => 'PARENT-LAW',
            'ingested_law_id' => $ingested->id,
            'local_path' => '/path/to/file.pdf',
        ]);
    }

    /**
     * Test editing LawUpload
     *
     * @test
     */
    public function it_edits_law_upload(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        $upload = LawUpload::create([
            'id' => (string) Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => 'PARENT-LAW',
            'disk' => 'local',
            'local_path' => '/old/path.pdf',
            'status' => 'stored',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->call('editUpload', $upload->id)
            ->assertSet('showUploadModal', true)
            ->assertSet('editingUpload.id', $upload->id)
            ->set('editingUpload.local_path', '/new/path.pdf')
            ->call('saveUpload')
            ->assertSet('showUploadModal', false);

        $this->assertDatabaseHas('law_uploads', [
            'id' => $upload->id,
            'local_path' => '/new/path.pdf',
        ]);
    }

    /**
     * Test deleting LawUpload
     *
     * @test
     */
    public function it_deletes_law_upload(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        $upload = LawUpload::create([
            'id' => (string) Str::ulid(),
            'ingested_law_id' => $ingested->id,
            'doc_id' => 'PARENT-LAW',
            'disk' => 'local',
            'local_path' => '/path.pdf',
            'status' => 'stored',
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('deleteUpload', $upload->id);

        $this->assertDatabaseMissing('law_uploads', [
            'id' => $upload->id,
        ]);
    }

    /**
     * Test tab switching
     *
     * @test
     */
    public function it_switches_between_tabs(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->assertSet('tab', 'laws')
            ->set('tab', 'uploads')
            ->assertSet('tab', 'uploads');
    }

    /**
     * Test opening scraper modal
     *
     * @test
     */
    public function it_opens_scraper_modal(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('openScraper')
            ->assertSet('showScraperModal', true)
            ->assertSet('scrapedLaws', [])
            ->assertSet('selectedLawsToImport', []);
    }

    /**
     * Test scraping laws
     *
     * @test
     */
    public function it_scrapes_laws_successfully(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $scraperMock = Mockery::mock('overload:'.ZakonHrScraper::class);
        $scraperMock->shouldReceive('getUniqueLaws')
            ->once()
            ->andReturn([
                ['title' => 'Law 1', 'url' => 'http://example.com/law1', 'law_number' => '123/2023'],
                ['title' => 'Law 2', 'url' => 'http://example.com/law2', 'law_number' => '124/2023'],
            ]);

        Livewire::test(IngestedLawsManager::class)
            ->call('startScraping')
            ->assertSet('isScraperLoading', false)
            ->assertCount('scrapedLaws', 2);
    }

    /**
     * Test scraping handles errors
     *
     * @test
     */
    public function it_handles_scraping_errors(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $scraperMock = Mockery::mock('overload:'.ZakonHrScraper::class);
        $scraperMock->shouldReceive('getUniqueLaws')
            ->once()
            ->andThrow(new \Exception('Scraping failed'));

        Livewire::test(IngestedLawsManager::class)
            ->call('startScraping')
            ->assertSet('isScraperLoading', false)
            ->assertDispatched('scraping-error');
    }

    /**
     * Test toggling law selection for import
     *
     * @test
     */
    public function it_toggles_law_selection(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $component = Livewire::test(IngestedLawsManager::class)
            ->set('scrapedLaws', [
                ['title' => 'Law 1', 'url' => 'http://example.com/law1'],
            ])
            ->call('toggleLawSelection', 'http://example.com/law1')
            ->assertCount('selectedLawsToImport', 1);

        // Toggle again to deselect
        $component->call('toggleLawSelection', 'http://example.com/law1')
            ->assertCount('selectedLawsToImport', 0);
    }

    /**
     * Test selecting all filtered laws
     *
     * @test
     */
    public function it_selects_all_filtered_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->set('scrapedLaws', [
                ['title' => 'Law 1', 'url' => 'http://example.com/law1'],
                ['title' => 'Law 2', 'url' => 'http://example.com/law2'],
                ['title' => 'Law 3', 'url' => 'http://example.com/law3'],
            ])
            ->call('selectAllFilteredLaws')
            ->assertCount('selectedLawsToImport', 3);
    }

    /**
     * Test deselecting all laws
     *
     * @test
     */
    public function it_deselects_all_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', ['url1', 'url2', 'url3'])
            ->call('deselectAllLaws')
            ->assertCount('selectedLawsToImport', 0);
    }

    /**
     * Test filtering scraped laws
     *
     * @test
     */
    public function it_filters_scraped_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $component = Livewire::test(IngestedLawsManager::class)
            ->set('scrapedLaws', [
                ['title' => 'Labor Law', 'url' => 'http://example.com/labor'],
                ['title' => 'Tax Law', 'url' => 'http://example.com/tax'],
                ['title' => 'Labor Rights', 'url' => 'http://example.com/rights'],
            ])
            ->set('scraperSearchFilter', 'Labor')
            ->instance();

        $filtered = $component->getFilteredScrapedLaws();
        $this->assertCount(2, $filtered);
    }

    /**
     * Test importing selected laws
     *
     * @test
     */
    public function it_imports_selected_laws(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $ingestServiceMock->shouldReceive('ingestUrls')
            ->once()
            ->with(['http://example.com/law1', 'http://example.com/law2'])
            ->andReturn([
                'urls_processed' => 2,
                'articles_seen' => 10,
                'inserted' => 8,
            ]);

        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', ['http://example.com/law1', 'http://example.com/law2'])
            ->call('importSelectedLaws')
            ->assertSet('isImporting', false)
            ->assertDispatched('import-complete');
    }

    /**
     * Test importing with no selection
     *
     * @test
     */
    public function it_handles_import_with_no_selection(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', [])
            ->call('importSelectedLaws')
            ->assertDispatched('import-error');
    }

    /**
     * Test import handles errors
     *
     * @test
     */
    public function it_handles_import_errors(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $ingestServiceMock->shouldReceive('ingestUrls')
            ->once()
            ->andThrow(new \Exception('Import failed'));

        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedLawsToImport', ['http://example.com/law1'])
            ->call('importSelectedLaws')
            ->assertSet('isImporting', false)
            ->assertDispatched('import-error');
    }

    /**
     * Test search resets pagination
     *
     * @test
     */
    public function it_resets_pagination_on_search(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        // Create enough laws for multiple pages
        for ($i = 0; $i < 25; $i++) {
            IngestedLaw::create([
                'id' => (string) Str::ulid(),
                'doc_id' => "LAW-{$i}",
                'title' => "Law {$i}",
                'ingested_at' => now(),
            ]);
        }

        $component = Livewire::test(IngestedLawsManager::class)
            ->call('gotoPage', 2);

        // Setting search should reset to page 1
        $component->set('search', 'LAW-1')
            ->assertViewHas('ingested', function ($ingested) {
                return $ingested->currentPage() === 1;
            });
    }

    /**
     * Test JSON encoding for array fields
     *
     * @test
     */
    public function it_handles_json_fields_in_ingested_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        Livewire::test(IngestedLawsManager::class)
            ->call('createIngested')
            ->set('editingIngested.doc_id', 'JSON-LAW')
            ->set('editingIngested.aliases', '["alias1", "alias2"]')
            ->set('editingIngested.keywords', '["keyword1", "keyword2"]')
            ->set('editingIngested.metadata', '{"key": "value"}')
            ->call('saveIngested');

        $law = IngestedLaw::where('doc_id', 'JSON-LAW')->first();
        $this->assertIsArray($law->aliases);
        $this->assertIsArray($law->keywords);
        $this->assertIsArray($law->metadata);
    }

    /**
     * Test deleting selected ingested law clears selection
     *
     * @test
     */
    public function it_clears_selection_when_deleting_selected_law(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $law = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'SELECTED-LAW',
            'title' => 'Selected',
            'ingested_at' => now(),
        ]);

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $law->id)
            ->call('deleteIngested', $law->id)
            ->assertSet('selectedIngestedId', null);
    }

    /**
     * Test component displays child laws when parent is selected
     *
     * @test
     */
    public function it_displays_child_laws_for_selected_parent(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        // Create 3 child laws
        for ($i = 0; $i < 3; $i++) {
            Law::create([
                'id' => (string) Str::ulid(),
                'ingested_law_id' => $ingested->id,
                'doc_id' => 'PARENT-LAW',
                'chunk_index' => $i,
                'content' => "Content {$i}",
                'embedding_provider' => 'manual',
                'embedding_model' => 'none',
                'embedding_dimensions' => 1536,
                'content_hash' => hash('sha256', "Content {$i}"),
            ]);
        }

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->assertViewHas('laws', function ($laws) {
                return $laws->count() === 3;
            });
    }

    /**
     * Test component displays child uploads when parent is selected
     *
     * @test
     */
    public function it_displays_child_uploads_for_selected_parent(): void
    {
        $ingestServiceMock = Mockery::mock(ZakonHrIngestService::class);
        $this->app->instance(ZakonHrIngestService::class, $ingestServiceMock);

        $ingested = IngestedLaw::create([
            'id' => (string) Str::ulid(),
            'doc_id' => 'PARENT-LAW',
            'title' => 'Parent',
            'ingested_at' => now(),
        ]);

        // Create 2 uploads
        for ($i = 0; $i < 2; $i++) {
            LawUpload::create([
                'id' => (string) Str::ulid(),
                'ingested_law_id' => $ingested->id,
                'doc_id' => 'PARENT-LAW',
                'disk' => 'local',
                'local_path' => "/path{$i}.pdf",
                'status' => 'stored',
            ]);
        }

        Livewire::test(IngestedLawsManager::class)
            ->set('selectedIngestedId', $ingested->id)
            ->assertViewHas('uploads', function ($uploads) {
                return $uploads->count() === 2;
            });
    }
}
