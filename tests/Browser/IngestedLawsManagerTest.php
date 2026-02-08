<?php

namespace Tests\Browser;

use App\Models\IngestedLaw;
use App\Models\Law;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * IngestedLawsManager E2E Test Suite
 *
 * Tests the Croatian law database management component that handles:
 * - IngestedLaw records (parent documents)
 * - Law records (chunked law articles/sections)
 * - LawUpload records (source files)
 * - zakon.hr web scraper integration
 */
class IngestedLawsManagerTest extends DuskTestCase
{
    use AuthenticatesUser;
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAIApis();
    }

    /**
     * Test that the laws manager page loads successfully
     */
    public function test_page_loads_successfully(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@laws-manager-container', 20)
                ->assertVisible('@laws-manager-container')
                ->assertVisible('@page-title')
                ->assertSeeIn('@page-title', 'Ingested Laws Manager')
                ->assertVisible('@law-search-input')
                ->assertVisible('@scrape-laws-button')
                ->assertVisible('@create-ingested-button');
        });
    }

    /**
     * Test that empty state displays when no laws exist
     */
    public function test_empty_state_displays_when_no_laws_exist(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@laws-manager-container', 20)
                ->assertVisible('@no-laws-message')
                ->assertSee('No ingested laws found');
        });
    }

    /**
     * Test that ingested laws list displays correctly
     */
    public function test_ingested_laws_list_displays_correctly(): void
    {
        // Create test laws with Croatian content
        $zkpLaw = IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
        ]);

        $ustavLaw = IngestedLaw::factory()->create([
            'title' => 'Ustav Republike Hrvatske',
            'law_number' => 'NN 56/90',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
        ]);

        $this->browse(function (Browser $browser) use ($zkpLaw, $ustavLaw) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->assertVisible('@ingested-law-0')
                ->assertVisible('@ingested-law-1')
                ->assertSeeIn('@ingested-title-0', $zkpLaw->title)
                ->assertSee($zkpLaw->law_number)
                ->assertSee($ustavLaw->title);
        });
    }

    /**
     * Test search functionality filters laws correctly
     */
    public function test_search_filters_laws_correctly(): void
    {
        IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
        ]);

        IngestedLaw::factory()->create([
            'title' => 'Zakon o obveznim odnosima',
            'law_number' => 'NN 35/05',
            'jurisdiction' => 'HR',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->assertVisible('@ingested-law-0')
                ->assertVisible('@ingested-law-1')
                ->type('@law-search-input', 'kaznenom')
                ->pause(500) // Wait for debounce
                ->waitUntilMissing('@ingested-law-1', 20)
                ->assertVisible('@ingested-law-0')
                ->assertSee('Zakon o kaznenom postupku')
                ->assertDontSee('Zakon o obveznim odnosima');
        });
    }

    /**
     * Test search with Croatian special characters
     */
    public function test_search_with_croatian_special_characters(): void
    {
        IngestedLaw::factory()->create([
            'title' => 'Zakon o građanskom postupku',
            'law_number' => 'NN 53/91',
            'jurisdiction' => 'HR',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->type('@law-search-input', 'građanskom')
                ->pause(500)
                ->assertSee('Zakon o građanskom postupku');
        });
    }

    /**
     * Test clear search button functionality
     */
    public function test_clear_search_button_works(): void
    {
        IngestedLaw::factory()->count(2)->create();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->type('@law-search-input', 'test search')
                ->pause(500)
                ->waitFor('@clear-search-button', 20)
                ->click('@clear-search-button')
                ->pause(500)
                ->assertInputValue('@law-search-input', '');
        });
    }

    /**
     * Test sorting by ingested_at field
     */
    public function test_sort_by_ingested_at_toggles_direction(): void
    {
        IngestedLaw::factory()->create([
            'title' => 'Old Law',
            'ingested_at' => now()->subDays(10),
        ]);

        IngestedLaw::factory()->create([
            'title' => 'New Law',
            'ingested_at' => now()->subDays(1),
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->assertSeeIn('@ingested-title-0', 'New Law') // Default: desc
                ->click('@sort-ingested-at')
                ->pause(500)
                ->assertSeeIn('@ingested-title-0', 'Old Law') // Changed to: asc
                ->click('@sort-ingested-at')
                ->pause(500)
                ->assertSeeIn('@ingested-title-0', 'New Law'); // Back to: desc
        });
    }

    /**
     * Test sorting by title field
     */
    public function test_sort_by_title_works(): void
    {
        IngestedLaw::factory()->create(['title' => 'Zakon B']);
        IngestedLaw::factory()->create(['title' => 'Zakon A']);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@sort-title')
                ->pause(500)
                ->assertSeeIn('@ingested-title-0', 'Zakon A')
                ->assertSeeIn('@ingested-title-1', 'Zakon B');
        });
    }

    /**
     * Test sorting by law number field
     */
    public function test_sort_by_law_number_works(): void
    {
        IngestedLaw::factory()->create([
            'title' => 'Law 1',
            'law_number' => 'NN 200/20',
        ]);

        IngestedLaw::factory()->create([
            'title' => 'Law 2',
            'law_number' => 'NN 100/20',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@sort-law-number')
                ->pause(500)
                ->assertSeeIn('@ingested-title-0', 'Law 2'); // NN 100/20
        });
    }

    /**
     * Test selecting an ingested law displays its details
     */
    public function test_select_ingested_law_displays_details(): void
    {
        $law = IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'language' => 'hr',
        ]);

        $this->browse(function (Browser $browser) use ($law) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertSee($law->title)
                ->assertSee($law->law_number)
                ->assertVisible('@tab-laws')
                ->assertVisible('@tab-uploads');
        });
    }

    /**
     * Test create ingested law modal opens
     */
    public function test_create_ingested_law_modal_opens(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@laws-manager-container', 20)
                ->click('@create-ingested-button')
                ->waitFor('@ingested-modal', 20)
                ->assertVisible('@ingested-modal')
                ->assertSeeIn('@ingested-modal-title', 'New Ingested Law')
                ->assertVisible('@ingested-doc-id-input')
                ->assertVisible('@ingested-law-number-input')
                ->assertVisible('@ingested-title-input')
                ->assertVisible('@save-ingested-button');
        });
    }

    /**
     * Test creating a new ingested law with Croatian content
     */
    public function test_create_new_ingested_law_with_croatian_content(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@laws-manager-container', 20)
                ->click('@create-ingested-button')
                ->waitFor('@ingested-modal', 20)
                ->type('@ingested-doc-id-input', 'zkp-2025')
                ->type('@ingested-law-number-input', 'NN 152/08')
                ->type('@ingested-title-input', 'Zakon o kaznenom postupku')
                ->click('@save-ingested-button')
                ->pause(1000)
                ->waitUntilMissing('@ingested-modal', 20)
                ->assertSee('Zakon o kaznenom postupku')
                ->assertSee('NN 152/08');
        });

        $this->assertDatabaseHas('ingested_laws', [
            'doc_id' => 'zkp-2025',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
        ]);
    }

    /**
     * Test editing an existing ingested law
     */
    public function test_edit_existing_ingested_law(): void
    {
        $law = IngestedLaw::factory()->create([
            'doc_id' => 'test-law-123',
            'title' => 'Original Title',
            'law_number' => 'NN 100/20',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@edit-ingested-0')
                ->waitFor('@ingested-modal', 20)
                ->assertSeeIn('@ingested-modal-title', 'Edit Ingested Law')
                ->assertInputValue('@ingested-doc-id-input', 'test-law-123')
                ->clear('@ingested-title-input')
                ->type('@ingested-title-input', 'Updated Title')
                ->click('@save-ingested-button')
                ->pause(1000)
                ->waitUntilMissing('@ingested-modal', 20)
                ->assertSee('Updated Title');
        });

        $this->assertDatabaseHas('ingested_laws', [
            'id' => $law->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test deleting an ingested law
     */
    public function test_delete_ingested_law(): void
    {
        $law = IngestedLaw::factory()->create([
            'title' => 'Law to Delete',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->assertSee('Law to Delete')
                ->click('@delete-ingested-0')
                ->pause(500)
                ->acceptDialog() // Accept confirmation dialog
                ->pause(1000)
                ->assertDontSee('Law to Delete');
        });

        $this->assertDatabaseMissing('ingested_laws', [
            'id' => $law->id,
        ]);
    }

    /**
     * Test switching between law chunks and uploads tabs
     */
    public function test_switch_between_tabs(): void
    {
        $law = IngestedLaw::factory()->create([
            'title' => 'Test Law',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertVisible('@laws-tab-content')
                ->assertVisible('@create-law-button')
                ->click('@tab-uploads')
                ->pause(500)
                ->assertVisible('@uploads-tab-content')
                ->assertVisible('@create-upload-button')
                ->click('@tab-laws')
                ->pause(500)
                ->assertVisible('@laws-tab-content');
        });
    }

    /**
     * Test law chunks tab displays law records correctly
     */
    public function test_law_chunks_tab_displays_laws(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'ZKP',
            'doc_id' => 'zkp-doc',
        ]);

        $lawChunk = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zkp-doc',
            'title' => 'Članak 9 - Načelo zakonitosti',
            'content' => 'Nitko ne može biti progonjen ili kažnjen osim na temelju zakona.',
            'chunk_index' => 0,
            'language' => 'hr',
            'metadata' => ['article_number' => '9'],
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertVisible('@laws-table')
                ->assertVisible('@law-row-0')
                ->assertSeeIn('@law-title-0', 'Članak 9 - Načelo zakonitosti')
                ->assertSee('Art. 9')
                ->assertSee('#0')
                ->assertSee('HR');
        });
    }

    /**
     * Test creating a new law chunk
     */
    public function test_create_new_law_chunk(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'ZKP',
            'doc_id' => 'zkp-doc',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->click('@create-law-button')
                ->waitFor('@law-modal', 20)
                ->assertVisible('@law-modal')
                ->assertSeeIn('@law-modal-title', 'New Law Chunk')
                ->assertInputValue('@law-doc-id-input', 'zkp-doc')
                ->type('@law-chunk-index-input', '0')
                ->type('@law-title-input', 'Članak 1')
                ->type('@law-content-input', 'Ovaj zakon uređuje kazneni postupak.')
                ->click('@save-law-button')
                ->pause(1000)
                ->waitUntilMissing('@law-modal', 20)
                ->assertSee('Članak 1');
        });

        $this->assertDatabaseHas('laws', [
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zkp-doc',
            'title' => 'Članak 1',
            'chunk_index' => 0,
        ]);
    }

    /**
     * Test viewing law chunk details modal
     */
    public function test_view_law_chunk_details_modal(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'ZKP',
            'doc_id' => 'zkp-doc',
        ]);

        $lawChunk = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zkp-doc',
            'title' => 'Članak 9',
            'content' => 'Nitko ne može biti progonjen ili kažnjen osim na temelju zakona.',
            'chunk_index' => 0,
            'metadata' => ['article_number' => '9', 'law_code' => 'ZKP'],
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->click('@view-law-0')
                ->waitFor('@law-details-modal', 20)
                ->assertVisible('@law-details-modal')
                ->assertSee('Članak 9')
                ->assertSee('Article 9')
                ->assertSee('Nitko ne može biti progonjen')
                ->assertSee('ZKP');
        });
    }

    /**
     * Test editing an existing law chunk
     */
    public function test_edit_existing_law_chunk(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'ZKP',
            'doc_id' => 'zkp-doc',
        ]);

        $lawChunk = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zkp-doc',
            'title' => 'Original Članak',
            'content' => 'Original content.',
            'chunk_index' => 0,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->click('@edit-law-0')
                ->waitFor('@law-modal', 20)
                ->assertSeeIn('@law-modal-title', 'Edit Law Chunk')
                ->clear('@law-title-input')
                ->type('@law-title-input', 'Updated Članak')
                ->clear('@law-content-input')
                ->type('@law-content-input', 'Updated content.')
                ->click('@save-law-button')
                ->pause(1000)
                ->waitUntilMissing('@law-modal', 20)
                ->assertSee('Updated Članak');
        });

        $this->assertDatabaseHas('laws', [
            'id' => $lawChunk->id,
            'title' => 'Updated Članak',
            'content' => 'Updated content.',
        ]);
    }

    /**
     * Test deleting a law chunk
     */
    public function test_delete_law_chunk(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'ZKP',
            'doc_id' => 'zkp-doc',
        ]);

        $lawChunk = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zkp-doc',
            'title' => 'Law to Delete',
            'content' => 'Content.',
            'chunk_index' => 0,
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertSee('Law to Delete')
                ->click('@delete-law-0')
                ->pause(500)
                ->acceptDialog()
                ->pause(1000)
                ->assertDontSee('Law to Delete');
        });

        $this->assertDatabaseMissing('laws', [
            'id' => $lawChunk->id,
        ]);
    }

    /**
     * Test empty state when no law chunks exist
     */
    public function test_empty_state_when_no_law_chunks(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'Empty Law',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertVisible('@laws-tab-content')
                ->assertSee('No law chunks yet');
        });
    }

    /**
     * Test Croatian law displays correctly with special characters
     */
    public function test_croatian_law_displays_with_special_characters(): void
    {
        $ingestedLaw = IngestedLaw::factory()->create([
            'title' => 'Zakon o građanskom postupku',
            'law_number' => 'NN 53/91',
            'jurisdiction' => 'Županijski sud',
        ]);

        $lawChunk = Law::factory()->create([
            'ingested_law_id' => $ingestedLaw->id,
            'doc_id' => 'zgp-doc',
            'title' => 'Članak 1 - Područje primjene',
            'content' => 'Ovaj zakon uređuje postupak u parničnim stvarima koje rješavaju redovni sudovi.',
            'chunk_index' => 0,
            'language' => 'hr',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/ingested-laws')
                ->waitFor('@ingested-laws-list', 20)
                ->assertSee('Zakon o građanskom postupku')
                ->assertSee('Županijski sud')
                ->click('@select-ingested-0')
                ->pause(500)
                ->assertSee('Članak 1 - Područje primjene')
                ->click('@view-law-0')
                ->waitFor('@law-details-modal', 20)
                ->assertSee('Ovaj zakon uređuje postupak u parničnim stvarima');
        });
    }
}
