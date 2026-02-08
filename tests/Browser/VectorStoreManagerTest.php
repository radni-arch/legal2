<?php

namespace Tests\Browser;

use App\Models\IngestedLaw;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class VectorStoreManagerTest extends DuskTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure required tables exist
        if (! Schema::hasTable('users') || ! Schema::hasTable('ingested_laws')) {
            $this->markTestSkipped('Database schema not initialized');
        }

        $this->user = User::factory()->create();
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
     * Test vector store browsing and pagination
     */
    public function test_vector_store_browsing(): void
    {
        // Create test vector data
        IngestedLaw::factory()->count(25)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/vectors/manage')
                ->waitForLivewire()
                ->assertSee('Vector Store Manager')
                // Should display store selector
                ->assertPresent('@store-selector')
                ->assertPresent('@store-option-laws')
                ->assertPresent('@store-option-decisions')
                ->assertPresent('@store-option-cases')
                ->assertPresent('@store-option-textract')
                // Should display statistics
                ->waitFor('@stats-panel', 20)
                ->assertSee('Statistics')
                ->assertSee('Total Documents')
                // Should display documents list
                ->waitFor('@documents-list', 20)
                ->assertPresent('@document-row')
                // Test pagination
                ->assertPresent('@pagination')
                // Should show 20 items per page by default
                ->assertSeeIn('@current-page', '1')
                // Click next page
                ->click('@next-page-btn')
                ->pause(1000)
                ->assertSeeIn('@current-page', '2')
                // Click previous page
                ->click('@prev-page-btn')
                ->pause(1000)
                ->assertSeeIn('@current-page', '1')
                // Test store switching
                ->click('@store-option-decisions')
                ->pause(1000)
                ->assertSeeIn('@selected-store', 'decisions');
        });
    }

    /**
     * Test vector similarity search
     */
    public function test_vector_search(): void
    {
        // Create test laws with known content
        IngestedLaw::factory()->create([
            'title' => 'Zakon o kaznenom postupku',
            'doc_id' => 'zkp-2008',
        ]);

        IngestedLaw::factory()->create([
            'title' => 'Kazneni zakon',
            'doc_id' => 'kz-2011',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/vectors/manage')
                ->waitForLivewire()
                ->waitFor('@documents-list', 20)
                // Select search type
                ->assertPresent('@search-type-select')
                ->select('@search-type-select', 'similarity')
                // Enter search query
                ->type('@search-input', 'kazneni postupak')
                ->click('@search-btn')
                ->pause(2000)
                // Should show search results
                ->waitFor('@search-results', 20)
                ->assertSee('similar documents')
                // Should display similarity scores
                ->assertPresent('@similarity-score')
                // Test content search
                ->select('@search-type-select', 'content')
                ->clear('@search-input')
                ->type('@search-input', 'Zakon')
                ->click('@search-btn')
                ->pause(1000)
                ->assertSee('Zakon')
                // Test reset search
                ->click('@reset-search-btn')
                ->pause(500)
                ->assertInputValue('@search-input', '')
                ->assertPresent('@documents-list');
        });
    }

    /**
     * Test document re-indexing functionality
     */
    public function test_re_indexing(): void
    {
        // Create test vector documents
        $law = IngestedLaw::factory()->create([
            'title' => 'Test Law for Re-indexing',
            'doc_id' => 'test-reindex-001',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/vectors/manage')
                ->waitForLivewire()
                ->waitFor('@documents-list', 20)
                // Find and select a document
                ->assertPresent('@document-row')
                ->click('@select-document-0') // Select first document
                ->pause(500)
                // Should show bulk action buttons
                ->assertPresent('@bulk-actions')
                ->assertPresent('@reindex-selected-btn')
                ->assertPresent('@delete-selected-btn')
                // Click re-index button
                ->click('@reindex-selected-btn')
                ->pause(2000)
                // Should show success message
                ->waitFor('@success-message', 20)
                ->assertSee('re-indexed')
                // Test single document re-index via context menu
                ->click('@document-actions-0')
                ->pause(500)
                ->assertPresent('@reindex-document-btn')
                ->click('@reindex-document-btn')
                ->pause(2000)
                ->waitFor('@success-message', 20)
                ->assertSee('re-indexed')
                // Test document preview
                ->click('@preview-document-0')
                ->pause(500)
                ->waitFor('@preview-modal', 15)
                ->assertSee('Document Preview')
                ->assertPresent('@preview-content')
                ->click('@close-preview-btn')
                ->waitUntilMissing('@preview-modal', 5);

            // Test select all functionality
            $browser->click('@select-all-checkbox')
                ->pause(500)
                ->assertChecked('@select-all-checkbox')
                // Deselect all
                ->click('@select-all-checkbox')
                ->pause(500)
                ->assertNotChecked('@select-all-checkbox');
        });
    }
}
