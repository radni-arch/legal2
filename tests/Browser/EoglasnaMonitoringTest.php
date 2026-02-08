<?php

namespace Tests\Browser;

use App\Models\EoglasnaOsijekMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EoglasnaMonitoringTest extends DuskTestCase
{
    use DatabaseMigrations;
    // ✅ No DatabaseTransactions - browser needs to see committed data

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test eoglasna monitoring dashboard displays correctly
     */
    public function test_eoglasna_monitoring_dashboard(): void
    {
        // Create test data
        EoglasnaOsijekMonitoring::factory()->count(5)->create();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/eoglasna')
                ->assertSee('Monitoring') // Match on partial text that's definitely present
                // Check tabs are present
                ->assertPresent('@tab-osijek')
                ->assertPresent('@tab-keywords')
                ->assertPresent('@tab-activity')
                // Default tab should be osijek
                ->assertAttribute('@tab-osijek', 'class', '*active*')
                // Wait for osijek list to load
                ->waitFor('@osijek-list', 15)
                // Switch to keywords tab
                ->click('@tab-keywords')
                ->waitFor('@keywords-list', 15)
                // Switch to activity tab
                ->click('@tab-activity')
                ->waitFor('@activity-list', 15);
        });
    }

    /**
     * Test keyword alerts functionality
     */
    public function test_keyword_alerts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/eoglasna?tab=keywords')
                ->waitFor('@keywords-list', 15)
                // Click create keyword button
                ->click('@create-keyword-btn')
                ->waitFor('@keyword-modal', 15)
                // Fill in keyword form
                ->type('@keyword-query', 'test keyword')
                ->select('@keyword-scope', 'notice')
                ->check('@keyword-enabled')
                ->type('@keyword-notes', 'Test keyword for monitoring')
                // Save keyword
                ->click('@save-keyword-btn')
                ->waitUntilMissing('@keyword-modal', 10)
                ->waitForText('test keyword', 10)
                ->assertSee('test keyword')
                // Search for keyword
                ->type('@search-keyword', 'test')
                ->pause(1000)
                ->assertSee('test keyword')
                // Verify keyword appears in list
                ->assertPresent('[wire\\:key*="keyword-"]');

            // Verify keyword was created in database
            $this->assertDatabaseHas('eoglasna_keywords', [
                'query' => 'test keyword',
                'scope' => 'notice',
                'enabled' => true,
            ]);
        });
    }

    /**
     * Test court notice export and filtering
     */
    public function test_court_notice_export(): void
    {
        // Create test osijek notices with specific data
        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Bankruptcy Notice - Osijek Court',
            'case_number' => 'ST-123/2024',
            'court_name' => 'Općinski sud u Osijeku',
        ]);

        EoglasnaOsijekMonitoring::factory()->create([
            'title' => 'Civil Case Notice',
            'case_number' => 'P-456/2024',
            'court_name' => 'Županijski sud u Osijeku',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/eoglasna')
                ->waitFor('@osijek-list', 15)
                // Should display notices
                ->assertSee('Bankruptcy Notice - Osijek Court')
                ->assertSee('ST-123/2024')
                // Test search/filter
                ->type('@search-osijek', 'Bankruptcy')
                ->pause(1000)
                ->assertSee('Bankruptcy Notice')
                ->assertDontSee('Civil Case Notice')
                // Clear search
                ->clear('@search-osijek')
                ->pause(1000)
                ->assertSee('Bankruptcy Notice')
                ->assertSee('Civil Case Notice')
                // Test pagination if more than 15 items
                ->assertPresent('@osijek-pagination');

            // Test case number search
            $browser->type('@search-osijek', 'ST-123')
                ->pause(1000)
                ->assertSee('ST-123/2024')
                ->assertDontSee('P-456/2024');
        });
    }
}
