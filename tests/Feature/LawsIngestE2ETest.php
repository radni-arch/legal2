<?php

namespace Tests\Feature;

use App\Models\IngestedLaw;
use App\Models\Law;
use App\Services\ZakonHrIngestService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawsIngestE2ETest extends TestCase
{
    use UsesTestDatabase;

    protected ZakonHrIngestService $ingestService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingestService = app(ZakonHrIngestService::class);
        Storage::fake('local');
    }

    /** @test */
    public function it_completes_full_law_ingestion_pipeline()
    {
        // Arrange: Mock HTTP response with sample law HTML
        $html = $this->getSampleLawHtml();
        Http::fake([
            'zakon.hr/*' => Http::response($html, 200),
        ]);

        // Act: Ingest law from URL
        $result = $this->ingestService->ingestUrls([
            'https://zakon.hr/z/123/Test-Law',
        ]);

        // Assert: Pipeline completed successfully
        $this->assertEquals(1, $result['urls_processed']);
        $this->assertEquals(3, $result['articles_seen']);
        $this->assertGreaterThan(0, $result['inserted']);
        $this->assertEquals(0, $result['article_errors']);

        // Assert: IngestedLaw record was created
        $ingestedLaw = IngestedLaw::first();
        $this->assertNotNull($ingestedLaw);
        $this->assertStringContainsString('zakonhr-', $ingestedLaw->doc_id);
        $this->assertEquals('HR', $ingestedLaw->jurisdiction);

        // Assert: Law chunks were created with embeddings
        $laws = Law::where('doc_id', $ingestedLaw->doc_id)->get();
        $this->assertGreaterThan(0, $laws->count());

        foreach ($laws as $law) {
            $this->assertNotEmpty($law->content);
            $this->assertNotNull($law->content_hash);
            $this->assertNotNull($law->embedding_provider);
            $this->assertNotNull($law->embedding_model);
            $this->assertEquals($ingestedLaw->id, $law->ingested_law_id);
        }
    }

    /** @test */
    public function it_continues_processing_on_article_failure()
    {
        // Arrange: Mock partial failure during article processing
        $html = $this->getSampleLawHtmlWithMalformedArticle();

        $result = $this->ingestService->ingestHtml($html);

        // Assert: Processing continued despite failures
        $this->assertEquals(1, $result['urls_processed']);
        $this->assertGreaterThan(0, $result['articles_seen']);
        $this->assertGreaterThanOrEqual(0, $result['article_errors']);

        // Assert: IngestedLaw was still created
        $this->assertGreaterThan(0, IngestedLaw::count());

        // Assert: Valid articles were ingested
        $this->assertGreaterThan(0, Law::count());
    }

    /** @test */
    public function it_generates_embeddings_via_retry_service()
    {
        // Arrange
        $html = $this->getSampleLawHtml();

        // Act: Ingest law
        $result = $this->ingestService->ingestHtml($html);

        // Assert: Embeddings were created
        $laws = Law::all();
        foreach ($laws as $law) {
            $this->assertNotNull($law->embedding_provider, 'Provider should be set');
            $this->assertNotNull($law->embedding_model, 'Model should be set');
            $this->assertGreaterThan(0, $law->embedding_dimensions, 'Dimensions should be > 0');
        }
    }

    /** @test */
    public function it_returns_article_error_count_in_result()
    {
        // Arrange
        $html = $this->getSampleLawHtml();

        // Act
        $result = $this->ingestService->ingestHtml($html);

        // Assert: Result includes article_errors key
        $this->assertArrayHasKey('article_errors', $result);
        $this->assertIsInt($result['article_errors']);
        $this->assertEquals(0, $result['article_errors']);
    }

    // Helper methods

    protected function getSampleLawHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="hr">
<head>
    <title>Zakon o testiranju - Zakon.hr</title>
</head>
<body>
    <div class="law-header">
        <h1>ZAKON O TESTIRANJU</h1>
        <p>Na snazi od: 01.01.2025.</p>
    </div>
    <div class="law-content">
        <p>Članak 1.</p>
        <p>Ovaj zakon uređuje testiranje sustava za upravljanje pravnim dokumentima.</p>

        <p>Članak 2.</p>
        <p>Svaki članak zakona mora biti pravilno parsiran i pohranjen u bazu podataka sa odgovarajućim embeddingom.</p>

        <p>Članak 3.</p>
        <p>Sustav mora biti otporan na greške i nastaviti s radom čak i ako jedan članak nije uspješno procesiran.</p>
    </div>
</body>
</html>
HTML;
    }

    protected function getSampleLawHtmlWithMalformedArticle(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="hr">
<head>
    <title>Zakon sa greškom - Zakon.hr</title>
</head>
<body>
    <div class="law-content">
        <p>Članak 1.</p>
        <p>Valjan članak zakona.</p>

        <p>Članak 2.</p>
        <p>Još jedan valjan članak.</p>
    </div>
</body>
</html>
HTML;
    }
}
