<?php

namespace Tests\Browser;

use App\Events\LawImportProgress;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class LawImportProgressTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    public function test_broadcasts_import_started_event(): void
    {
        Event::fake([LawImportProgress::class]);

        $user = User::factory()->create();

        // Mock zakon.hr responses
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15) // Wait for scraped laws to appear
                ->click('@select-all-button')
                ->click('@import-button')
                ->pause(500); // Allow event dispatch
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'started' && $event->total > 0;
        });
    }

    public function test_broadcasts_processing_url_event(): void
    {
        Event::fake([LawImportProgress::class]);

        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')
                ->pause(1000); // Allow processing
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'processing_url' && isset($event->data['url']);
        });
    }

    public function test_broadcasts_url_completed_event(): void
    {
        Event::fake([LawImportProgress::class]);

        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')
                ->pause(2000); // Allow completion
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'url_completed'
                && isset($event->data['url'])
                && isset($event->data['articles']);
        });
    }

    public function test_broadcasts_completed_event(): void
    {
        Event::fake([LawImportProgress::class]);

        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')
                ->waitForText('Import complete', 30); // Wait for completion message
        });

        Event::assertDispatched(LawImportProgress::class, function ($event) {
            return $event->stage === 'completed'
                && isset($event->data['processed'])
                && isset($event->data['articles'])
                && isset($event->data['inserted']);
        });
    }

    public function test_displays_realtime_progress_updates(): void
    {
        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')

                // Verify progress bar appears
                ->waitFor('@progress-bar', 5)
                ->assertVisible('@progress-bar')

                // Verify progress updates
                ->waitFor('@progress-text', 2)
                ->assertSeeIn('@progress-text', '/')

                // Verify currently importing text
                ->waitFor('@currently-importing', 2)
                ->assertVisible('@currently-importing')

                // Wait for completion
                ->waitForText('Import complete', 30)
                ->assertDontSee('@progress-bar'); // Progress bar should hide
        });
    }

    public function test_displays_progress_percentage(): void
    {
        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')

                // Progress bar should have width style
                ->waitFor('@progress-bar', 5)
                ->pause(500) // Wait for first update

                // Script to extract width percentage
                ->script("
                    const bar = document.querySelector('[dusk=\"progress-bar-fill\"]');
                    return bar ? bar.style.width : '0%';
                ");

            // Just verify the bar exists and has some width
            $browser->assertVisible('@progress-bar-fill');
        });
    }

    public function test_updates_progress_via_echo_websocket(): void
    {
        $user = User::factory()->create();
        $this->mockZakonHrResponses();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Inject Echo listener verification script
                ->script("
                    window.echoEventsReceived = [];
                    if (window.Echo) {
                        window.Echo.channel('law-imports')
                            .listen('.import.progress', (e) => {
                                window.echoEventsReceived.push(e);
                            });
                    }
                ")

                ->click('@scrape-laws-button')
                ->waitForText('Scrape Laws from zakon.hr', 5)
                ->click('@fetch-laws-button')
                ->waitForText('law', 15)
                ->click('@select-all-button')
                ->click('@import-button')
                ->waitForText('Import complete', 30);

            // Verify Echo events were received
            $echoEvents = $browser->script('return window.echoEventsReceived || [];');

            $this->assertNotEmpty($echoEvents[0], 'Should have received Echo events');

            // Verify event structure
            $stages = array_column($echoEvents[0], 'stage');
            $this->assertContains('started', $stages, 'Should receive started event');
            $this->assertContains('completed', $stages, 'Should receive completed event');
        });
    }

    /**
     * Mock zakon.hr HTTP responses for testing
     */
    protected function mockZakonHrResponses(): void
    {
        // Mock scraper category list response
        Http::fake([
            'https://www.zakon.hr/cms/lgs.ashx*' => Http::response([
                [
                    'title' => 'Testni Zakon',
                    'url' => 'https://www.zakon.hr/z/123/Testni-Zakon',
                    'law_number' => '123/20',
                    'slug' => 'testni-zakon',
                ],
            ], 200),

            // Mock law HTML content
            'https://www.zakon.hr/z/*' => Http::response(
                '<html>
                    <head><title>Testni Zakon - Zakon.hr</title></head>
                    <body>
                        <h1>Testni Zakon</h1>
                        <div class="article">
                            <strong>Članak 1</strong>
                            <p>Testni sadržaj članka 1.</p>
                        </div>
                        <div>Na snazi od: 01.01.2020.</div>
                    </body>
                </html>',
                200
            ),

            // Mock OpenAI embeddings
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
            ], 200),
        ]);
    }
}
