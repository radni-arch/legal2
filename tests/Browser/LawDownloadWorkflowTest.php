<?php

namespace Tests\Browser;

use App\Models\IngestedLaw;
use App\Models\Law;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

class LawDownloadWorkflowTest extends DuskTestCase
{
    use AuthenticatesUser, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test complete workflow: Create law data, navigate, search, view details
     */
    public function test_complete_law_workflow_from_creation_to_view(): void
    {
        $user = User::factory()->create();

        // Create test Croatian law records
        $zkpLaw = $this->createZkpLaw();
        $ustavLaw = $this->createUstavLaw();
        $kaznenizakonLaw = $this->createKazneniZakonLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            // Navigate to ingested laws page
            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)
                ->assertSee('Ingested Laws Manager')
                ->assertSee('Search')

                // Verify page loaded with search controls
                ->assertPresent('@law-search-input')

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)

                // Wait for search results to appear
                ->waitForText('Zakon o kaznenom postupku', 10)
                ->assertSee('Zakon o kaznenom postupku')
                ->assertSee('ZKP')

                // Click to select the law (use click for Livewire button instead of clickLink for <a> tags)
                ->click('li button:contains("Zakon o kaznenom postupku")')
                ->pause(500)

                // Verify law details are shown in right panel
                ->waitForText('Law Chunks', 5)
                ->assertSee('Law Chunks')

                // Verify law chunks are displayed
                ->waitForText('Članak 9', 10)
                ->assertSee('Članak 9')
                ->assertSee('nezakonitost');
        });
    }

    /**
     * Test searching for specific law articles
     */
    public function test_search_specific_law_articles(): void
    {
        $user = User::factory()->create();
        $zkpLaw = $this->createZkpLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for specific article
                ->type('@law-search-input', 'Članak 9')
                ->pause(500)

                // Verify article content appears
                ->waitForText('Članak 9', 10)
                ->assertSee('Članak 9')
                ->assertSee('nezakonitost');
        });
    }

    /**
     * Test viewing law details modal
     */
    public function test_view_law_details_modal(): void
    {
        $user = User::factory()->create();
        $zkpLaw = $this->createZkpLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)

                // Wait for and click the law to select it
                ->waitForText('Zakon o kaznenom postupku', 10)
                ->click('li button:contains("Zakon o kaznenom postupku")')
                ->pause(500)

                // Wait for law chunks to load
                ->waitForText('Law Chunks', 5)

                // Click view details button
                ->click('@view-law-details')
                ->pause(300)

                // Wait for modal to appear
                ->waitFor('@law-details-modal', 5)

                // Verify modal shows law content
                ->assertSee('View Law Chunk')
                ->assertSee('Članak 9')
                ->assertSee('nezakonitost')
                ->assertSee('Article 9')
                ->assertSee('Content:');
        });
    }

    /**
     * Test searching Ustav RH (Croatian Constitution)
     */
    public function test_search_croatian_constitution(): void
    {
        $user = User::factory()->create();
        $ustavLaw = $this->createUstavLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for Ustav
                ->type('@law-search-input', 'Ustav')
                ->pause(500)

                // Verify Constitution appears
                ->waitForText('Ustav Republike Hrvatske', 10)
                ->assertSee('Ustav Republike Hrvatske')
                ->assertSee('Republika Hrvatska')

                // Click to view details
                ->click('li button:contains("Ustav Republike Hrvatske")')
                ->pause(500)

                // Verify law chunks appear
                ->waitForText('Law Chunks', 5)
                ->assertSee('Članak 1')
                ->assertSee('demokratska i socijalna država');
        });
    }

    /**
     * Test searching Kazneni zakon (Criminal Code)
     */
    public function test_search_criminal_code(): void
    {
        $user = User::factory()->create();
        $kaznenizakonLaw = $this->createKazneniZakonLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for Kazneni zakon
                ->type('@law-search-input', 'Kazneni zakon')
                ->pause(500)

                // Verify Criminal Code appears
                ->waitForText('Kazneni zakon', 10)
                ->assertSee('Kazneni zakon')

                // Click to view
                ->click('li button:contains("Kazneni zakon")')
                ->pause(500)

                // Verify law chunks appear
                ->waitForText('Law Chunks', 5)
                ->assertSee('Članak 2');
        });
    }

    /**
     * Test clearing search
     */
    public function test_clear_search_filter(): void
    {
        $user = User::factory()->create();
        $zkpLaw = $this->createZkpLaw();
        $ustavLaw = $this->createUstavLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)

                // Verify ZKP appears
                ->waitForText('Zakon o kaznenom postupku', 10)
                ->assertSee('Zakon o kaznenom postupku')

                // Verify clear button appears and click it
                ->assertSee('Clear Search')
                ->click('button:contains("Clear Search")')
                ->pause(300)

                // Verify both laws appear after clearing
                ->waitForText('Ustav Republike Hrvatske', 10)
                ->assertSee('Ustav Republike Hrvatske')
                ->assertSee('Zakon o kaznenom postupku');
        });
    }

    /**
     * Test law chunks pagination and navigation
     */
    public function test_law_chunks_display_and_metadata(): void
    {
        $user = User::factory()->create();
        $zkpLaw = $this->createZkpLaw();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)

                // Click to select
                ->waitForText('Zakon o kaznenom postupku', 10)
                ->click('li button:contains("Zakon o kaznenom postupku")')
                ->pause(500)

                // Wait for law chunks table
                ->waitForText('Law Chunks', 5)

                // Verify table headers
                ->assertSee('ID')
                ->assertSee('Title')
                ->assertSee('Article #')
                ->assertSee('Chunk')
                ->assertSee('Lang')
                ->assertSee('Actions')

                // Verify article number badge
                ->assertSee('Art. 9')

                // Verify chunk index
                ->assertSee('#0')

                // Verify language
                ->assertSee('HR');
        });
    }

    /**
     * Test multiple law articles in same document
     */
    public function test_multiple_law_articles_display(): void
    {
        $user = User::factory()->create();

        // Create ingested law with multiple articles
        $ingestedLaw = IngestedLaw::factory()
            ->withTitle('Zakon o kaznenom postupku')
            ->withLawNumber('NN 152/08')
            ->croatian()
            ->create([
                'keywords' => ['criminal', 'procedure', 'ZKP'],
                'keywords_text' => 'criminal procedure ZKP',
                'metadata' => [
                    'law_code' => 'ZKP',
                    'aliases' => ['Criminal Procedure Act'],
                ],
            ]);

        // Create multiple law chunks for different articles
        $article9 = $this->createLawChunk($ingestedLaw, 9, 'Načelo zakonitosti',
            'Članak 9. Kazneno djelo i kazna moraju biti određeni zakonom. Zabranjuje se primjena zakona po analogiji.');

        $article10 = $this->createLawChunk($ingestedLaw, 10, 'Načelo proporcionalnosti',
            'Članak 10. Mjere iz ovog zakona moraju biti proporcionalne cilju koji se želi postići.');

        $article11 = $this->createLawChunk($ingestedLaw, 11, 'Načelo hitnosti',
            'Članak 11. Kazneni postupak mora se voditi bez odugovlačenja.');

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/ingested-laws')
                ->waitForText('Ingested Laws Manager', 10)

                // Search for ZKP
                ->type('@law-search-input', 'ZKP')
                ->pause(500)

                // Select the law
                ->waitForText('Zakon o kaznenom postupku', 10)
                ->click('li button:contains("Zakon o kaznenom postupku")')
                ->pause(500)

                // Wait for law chunks
                ->waitForText('Law Chunks', 5)

                // Verify all three articles appear
                ->assertSee('Art. 9')
                ->assertSee('Art. 10')
                ->assertSee('Art. 11')

                // Verify chunk indices
                ->assertSee('#0')
                ->assertSee('#1')
                ->assertSee('#2');
        });
    }

    /**
     * Create ZKP (Criminal Procedure Act) test data
     */
    protected function createZkpLaw(): IngestedLaw
    {
        $ingestedLaw = IngestedLaw::factory()
            ->withTitle('Zakon o kaznenom postupku')
            ->withLawNumber('NN 152/08')
            ->croatian()
            ->create([
                'keywords' => ['criminal', 'procedure', 'ZKP'],
                'keywords_text' => 'criminal procedure ZKP',
                'metadata' => [
                    'law_code' => 'ZKP',
                    'aliases' => ['Criminal Procedure Act'],
                ],
            ]);

        // Create law chunk for Članak 9 (Article 9 - Principle of Legality)
        $this->createLawChunk(
            $ingestedLaw,
            9,
            'Načelo zakonitosti',
            'Članak 9. Kazneno djelo i kazna moraju biti određeni zakonom. Zabranjuje se primjena zakona po analogiji. Nitko ne može biti kažnjen za djelo koje prije nego što je počinjeno nije bilo određeno zakonom kao kazneno djelo, niti mu se može izreći kazna koja za to djelo nije bila propisana zakonom. Dovoljno je utvrditi da je nezakonitost postojala u vrijeme izvršenja djela.'
        );

        return $ingestedLaw;
    }

    /**
     * Create Ustav RH (Croatian Constitution) test data
     */
    protected function createUstavLaw(): IngestedLaw
    {
        $ingestedLaw = IngestedLaw::factory()
            ->withTitle('Ustav Republike Hrvatske')
            ->withLawNumber('NN 56/90')
            ->croatian()
            ->create([
                'keywords' => ['constitution', 'ustav', 'fundamental', 'rights'],
                'keywords_text' => 'constitution ustav fundamental rights',
                'metadata' => [
                    'law_code' => 'Ustav RH',
                    'aliases' => ['Constitution of the Republic of Croatia'],
                ],
            ]);

        // Create law chunk for Članak 1 (Article 1 - Constitutional Foundations)
        $this->createLawChunk(
            $ingestedLaw,
            1,
            'Temeljna odredba',
            'Članak 1. Republika Hrvatska je jedinstvena i nedjeljiva demokratska i socijalna država. U Republici Hrvatskoj vlast proizlazi iz naroda i pripada narodu kao zajednici slobodnih i jednakih građana. Narod svoju vlast ostvaruje izborom svojih predstavnika i neposrednim odlučivanjem.'
        );

        return $ingestedLaw;
    }

    /**
     * Create Kazneni zakon (Criminal Code) test data
     */
    protected function createKazneniZakonLaw(): IngestedLaw
    {
        $ingestedLaw = IngestedLaw::factory()
            ->withTitle('Kazneni zakon')
            ->withLawNumber('NN 125/11')
            ->croatian()
            ->create([
                'keywords' => ['criminal', 'code', 'kazneni'],
                'keywords_text' => 'criminal code kazneni',
                'metadata' => [
                    'law_code' => 'KZ',
                    'aliases' => ['Criminal Code'],
                ],
            ]);

        // Create law chunk for Članak 2 (Article 2 - Principle of Legality)
        $this->createLawChunk(
            $ingestedLaw,
            2,
            'Načelo zakonitosti',
            'Članak 2. Kazneno djelo jest ono djelo koje je zakonom određeno kao kazneno djelo. Nikome se ne može izreći kazna ili druga sankcija propisana ovim zakonom ako to prije nego što je djelo počinio nije bilo zakonom ili međunarodnim ugovorom određeno kao kazneno djelo, niti mu se može izreći kazna ili druga sankcija propisana za to djelo.'
        );

        return $ingestedLaw;
    }

    /**
     * Helper method to create a law chunk
     */
    protected function createLawChunk(IngestedLaw $ingestedLaw, int $articleNumber, string $articleTitle, string $content, int $chunkIndex = 0): Law
    {
        $law = new Law([
            'id' => (string) Str::ulid(),
            'doc_id' => $ingestedLaw->doc_id,
            'ingested_law_id' => $ingestedLaw->id,
            'title' => $articleTitle,
            'law_number' => $ingestedLaw->law_number,
            'jurisdiction' => $ingestedLaw->jurisdiction,
            'country' => $ingestedLaw->country,
            'language' => $ingestedLaw->language,
            'chunk_index' => $chunkIndex,
            'content' => $content,
            'metadata' => [
                'article_number' => $articleNumber,
                'article_title' => $articleTitle,
                'law_code' => $ingestedLaw->metadata['law_code'] ?? null,
            ],
        ]);

        // Let the model set defaults for embedding fields
        $law->save();

        return $law;
    }
}
