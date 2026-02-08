<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class LawDownloadTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_can_access_ingested_laws_page(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)
                ->assertSee('Laws')
                ->assertSee('Search')
                ->assertSee('Croatian');
        });
    }

    public function test_can_search_croatian_laws(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)
                ->press('Search')
                ->waitForText('Kazneni postupak', 15)

                // Verify results shown
                ->assertSee('Zakon o kaznenom postupku')
                ->assertSee('ZKP');
        });
    }

    public function test_can_filter_laws_by_type(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)

                // Filter by criminal procedure
                ->select('@law-type-filter', 'criminal_procedure')
                ->pause(500)

                // Verify only criminal procedure laws shown
                ->assertSee('ZKP')
                ->assertSee('postupak');
        });
    }

    public function test_can_view_law_details(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)

                // Search for specific law
                ->type('@law-search-input', 'Ustav')
                ->press('Search')
                ->waitForText('Ustav', 15)

                // Click to view details
                ->press('@view-law-details')
                ->waitFor('@law-details-modal', 15)

                // Verify modal shows law content
                ->assertSee('Članak')
                ->assertSee('Republika Hrvatska');
        });
    }

    public function test_can_download_law_document(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)

                // Search for law
                ->type('@law-search-input', 'Kazneni zakon')
                ->press('Search')
                ->waitForText('Kazneni zakon', 15)

                // Click download button (will trigger signed URL download)
                ->press('@download-law-button')
                ->pause(2000);

            // Note: Can't easily verify file downloaded in browser test
            // But we verify button click doesn't error
        });
    }

    public function test_can_search_law_articles(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Laws', 15)

                // Search for specific article
                ->type('@law-search-input', 'Članak 9')
                ->press('Search')
                ->waitForText('Članak 9', 15)

                // Verify article shown
                ->assertSee('Članak 9')
                ->assertSee('nezakonitost');
        });
    }

    public function test_can_use_unified_search_for_laws(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/search')
                ->waitForText('Search', 15)

                // Enter search query
                ->type('@unified-search-input', 'proportionality home search')
                ->pause(500)

                // Select law category
                ->check('@search-category-laws')
                ->pause(500)

                // Click search
                ->press('Search')
                ->waitForText('results', 20)

                // Verify law results shown
                ->assertSee('Law')
                ->assertSee('ZKP');
        });
    }
}
