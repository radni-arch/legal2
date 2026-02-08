<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * EpredmetWidget E2E Test Suite
 *
 * Tests the Croatian E-Court (EKOM) case lookup widget that displays
 * case data via GraphQL API integration.
 */
class EpredmetWidgetTest extends DuskTestCase
{
    use AuthenticatesUser;

    // Temporarily disabled DatabaseMigrations due to PostgreSQL type conflicts
    // use DatabaseMigrations;
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAIApis();
    }

    /**
     * Test that the widget loads successfully on dashboard
     */
    public function test_widget_loads_successfully_on_dashboard(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->assertVisible('@epredmet-widget')
                ->assertVisible('@widget-title')
                ->assertSeeIn('@widget-title', 'e‑Predmet – GraphQL')
                ->assertVisible('@fetch-form')
                ->assertVisible('@sud-input')
                ->assertVisible('@oznaka-broj-input')
                ->assertVisible('@fetch-button')
                ->assertVisible('@clear-button');
        });
    }

    /**
     * Test that widget displays initial case data on mount
     */
    public function test_widget_displays_initial_case_data_on_mount(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@case-data', 10)
                ->assertVisible('@case-data')
                ->assertVisible('@oznaka-broj-value')
                ->assertSeeIn('@oznaka-broj-value', 'Pp Prz-74/2025')
                ->assertVisible('@upisnik-value')
                ->assertSeeIn('@upisnik-value', 'Prekršajni postupak')
                ->assertVisible('@vrsta-predmeta-value')
                ->assertSeeIn('@vrsta-predmeta-value', 'Prekršajni predmet');
        });
    }

    /**
     * Test fetching case data with custom inputs
     */
    public function test_fetch_case_data_with_custom_inputs(): void
    {
        $customData = [
            'oznakaBroj' => 'K-555/2025',
            'upisnikNaziv' => 'Kazneni postupak',
            'vrstaPredmeta' => 'Kazneni predmet',
        ];

        $this->mockGraphQLApi($customData);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->clear('@sud-input')
                ->type('@sud-input', '5108')
                ->clear('@oznaka-broj-input')
                ->type('@oznaka-broj-input', 'K-555/2025')
                ->click('@fetch-button')
                ->waitFor('@case-data', 10)
                ->assertSeeIn('@oznaka-broj-value', 'K-555/2025')
                ->assertSeeIn('@upisnik-value', 'Kazneni postupak')
                ->assertSeeIn('@vrsta-predmeta-value', 'Kazneni predmet');
        });
    }

    /**
     * Test clear button functionality
     */
    public function test_clear_button_clears_case_data(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@case-data', 10)
                ->assertVisible('@case-data')
                ->click('@clear-button')
                ->pause(500)
                ->assertMissing('@case-data');
        });
    }

    /**
     * Test validation error for empty sud field
     */
    public function test_validation_error_for_empty_sud_field(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->clear('@sud-input')
                ->click('@fetch-button')
                ->waitFor('@sud-error', 5)
                ->assertVisible('@sud-error');
        });
    }

    /**
     * Test validation error for empty oznakaBroj field
     */
    public function test_validation_error_for_empty_oznaka_broj_field(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->clear('@oznaka-broj-input')
                ->click('@fetch-button')
                ->waitFor('@oznaka-broj-error', 5)
                ->assertVisible('@oznaka-broj-error');
        });
    }

    /**
     * Test GraphQL API error handling
     */
    public function test_graphql_api_error_handling(): void
    {
        $this->mockGraphQLApiError('Case not found in EKOM system');

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@error-alert', 10)
                ->assertVisible('@error-alert')
                ->assertVisible('@error-message')
                ->assertSeeIn('@error-message', 'Case not found in EKOM system');
        });
    }

    /**
     * Test display of key dates (ključni datumi)
     */
    public function test_display_of_key_dates(): void
    {
        $dataWithDates = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'datumDodjele' => '2025-01-10 09:00:00',
            'datumDonosenjaOdluke' => '2025-02-20 14:30:00',
            'datumOtpreme' => '2025-02-21 10:00:00',
        ];

        $this->mockGraphQLApi($dataWithDates);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@dates-panel', 10)
                ->assertVisible('@dates-panel')
                ->assertVisible('@date-datumDodjele')
                ->assertSeeIn('@date-datumDodjele', '2025-01-10')
                ->assertVisible('@date-datumDonosenjaOdluke')
                ->assertSeeIn('@date-datumDonosenjaOdluke', '2025-02-20');
        });
    }

    /**
     * Test display of rocista (hearings) table
     */
    public function test_display_of_rocista_hearings_table(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@rocista-panel', 10)
                ->assertVisible('@rocista-panel')
                ->assertVisible('@rocista-table')
                ->assertVisible('@rociste-0')
                ->assertSeeIn('@rociste-0', 'Glavna rasprava')
                ->assertSeeIn('@rociste-0', '2025-02-15')
                ->assertSeeIn('@rociste-0', 'Sudnica 1');
        });
    }

    /**
     * Test display of stranke (parties) with collapsible section
     */
    public function test_display_of_stranke_parties(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@stranke-panel', 10)
                ->assertVisible('@stranke-panel')
                ->assertVisible('@stranke-toggle')
                ->assertVisible('@stranke-content')
                ->assertVisible('@stranka-0')
                ->assertSeeIn('@stranka-0', 'Ivana Horvat')
                ->assertSeeIn('@stranka-0', 'Okrivljenik')
                ->assertVisible('@stranka-1')
                ->assertSeeIn('@stranka-1', 'Republika Hrvatska');
        });
    }

    /**
     * Test collapsing stranke section
     */
    public function test_collapsing_stranke_section(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@stranke-panel', 10)
                ->assertVisible('@stranke-content')
                ->click('@stranke-toggle')
                ->pause(500)
                ->assertNotVisible('@stranke-content')
                ->click('@stranke-toggle')
                ->pause(500)
                ->assertVisible('@stranke-content');
        });
    }

    /**
     * Test display of pismena (documents) table
     */
    public function test_display_of_pismena_documents_table(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@pismena-panel', 10)
                ->assertVisible('@pismena-panel')
                ->assertVisible('@pismena-toggle')
                ->assertVisible('@pismena-content')
                ->assertVisible('@pismena-table')
                ->assertVisible('@pismeno-0')
                ->assertSeeIn('@pismeno-0', 'Zahtjev')
                ->assertSeeIn('@pismeno-0', 'Odvjetnik Petar Novak');
        });
    }

    /**
     * Test display of vjecnici (council members)
     */
    public function test_display_of_vjecnici_council_members(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@vjecnici-panel', 10)
                ->assertVisible('@vjecnici-panel')
                ->assertVisible('@vjecnik-0')
                ->assertSeeIn('@vjecnik-0', 'Marko Kovačević')
                ->assertSeeIn('@vjecnik-0', 'Sudac pojedinac');
        });
    }

    /**
     * Test display of povezani predmeti (related cases)
     */
    public function test_display_of_povezani_predmeti_related_cases(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@povezani-predmeti-panel', 10)
                ->assertVisible('@povezani-predmeti-panel')
                ->assertVisible('@povezani-predmet-0')
                ->assertSeeIn('@povezani-predmet-0', 'Pp Prz-73/2025')
                ->assertSeeIn('@povezani-predmet-0', 'Povezan predmet')
                ->assertSeeIn('@povezani-predmet-0', 'Sličan');
        });
    }

    /**
     * Test display of spis status (file status)
     */
    public function test_display_of_spis_status(): void
    {
        $dataWithStatus = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'spisNaVisemSudu' => true,
            'spisIzvanSuda' => false,
        ];

        $this->mockGraphQLApi($dataWithStatus);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@spis-status-card', 10)
                ->assertVisible('@spis-status-value')
                ->assertSeeIn('@spis-status-value', 'Visi sud: da')
                ->assertSeeIn('@spis-status-value', 'Izvan suda: ne');
        });
    }

    /**
     * Test request duration display
     */
    public function test_request_duration_display(): void
    {
        $this->mockGraphQLApi();

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@request-duration', 10)
                ->assertVisible('@request-duration')
                ->assertSeeIn('@request-duration', 'ms');
        });
    }

    /**
     * Test Croatian character display (č, ć, š, ž, đ)
     */
    public function test_croatian_character_display(): void
    {
        $croatianData = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'upisnikNaziv' => 'Prekršajni postupak',
            'stranke' => [
                [
                    'naziv' => 'Marko Čović',
                    'nazivuloge' => 'Okrivljenik',
                ],
                [
                    'naziv' => 'Ivana Šimić',
                    'nazivuloge' => 'Svjedok',
                ],
            ],
            'vjecnici' => [
                [
                    'ime' => 'Petar Đukić, sudac',
                    'vrsta' => 'Sudac',
                ],
            ],
        ];

        $this->mockGraphQLApi($croatianData);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->waitFor('@stranke-panel', 10)
                ->assertSeeIn('@stranka-0', 'Čović')
                ->assertSeeIn('@stranka-1', 'Šimić')
                ->waitFor('@vjecnici-panel', 10)
                ->assertSeeIn('@vjecnik-0', 'Đukić');
        });
    }

    /**
     * Test widget with no rocista (hearings)
     */
    public function test_widget_with_no_rocista(): void
    {
        $dataWithoutRocista = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'rocista' => [],
        ];

        $this->mockGraphQLApi($dataWithoutRocista);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->pause(1000)
                ->assertMissing('@rocista-panel');
        });
    }

    /**
     * Test widget with no stranke (parties)
     */
    public function test_widget_with_no_stranke(): void
    {
        $dataWithoutStranke = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'stranke' => [],
        ];

        $this->mockGraphQLApi($dataWithoutStranke);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->pause(1000)
                ->assertMissing('@stranke-panel');
        });
    }

    /**
     * Test widget with no pismena (documents)
     */
    public function test_widget_with_no_pismena(): void
    {
        $dataWithoutPismena = [
            'oznakaBroj' => 'Pp Prz-74/2025',
            'pismena' => [],
        ];

        $this->mockGraphQLApi($dataWithoutPismena);

        $this->browse(function (Browser $browser) {
            $this->loginAs($browser);

            $browser->visit('/dashboard')
                ->waitFor('@epredmet-widget', 10)
                ->pause(1000)
                ->assertMissing('@pismena-panel');
        });
    }
}
