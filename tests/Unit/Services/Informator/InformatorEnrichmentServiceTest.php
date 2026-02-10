<?php

namespace Tests\Unit\Services\Informator;

use App\Models\IngestedLaw;
use App\Services\Informator\InformatorClient;
use App\Services\Informator\InformatorEnrichmentService;
use Mockery;
use Tests\TestCase;
class InformatorEnrichmentServiceTest extends TestCase
{

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_enrich_stores_results_on_ingested_law_metadata(): void
    {
        // Arrange: Create an IngestedLaw
        $ingestedLaw = IngestedLaw::create([
            'title' => 'Zakon o obveznim odnosima',
            'law_number' => 'NN 35/2005',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'source_url' => 'https://example.com/zoo',
            'metadata' => ['source' => 'NN'],
            'ingested_at' => now(),
        ]);

        // Mock InformatorClient to return enrichment data
        $mockClient = Mockery::mock(InformatorClient::class);
        $mockClient->shouldReceive('fetchListingHtml')
            ->once()
            ->andReturn('<html><body>Mock listing with cross-references</body></html>');
        $mockClient->shouldReceive('parseListingForItems')
            ->once()
            ->andReturn([
                ['id' => '12345', 'href' => '/sudske-odluke/12345', 'text' => 'Related decision 1'],
                ['id' => '67890', 'href' => '/sudske-odluke/67890', 'text' => 'Related decision 2'],
            ]);

        // Enable enrichment feature flag
        config(['services.informator.enrichment_enabled' => true]);

        $service = new InformatorEnrichmentService($mockClient);

        // Act
        $service->enrich($ingestedLaw);

        // Assert: Enrichment data stored in metadata
        $ingestedLaw->refresh();
        $metadata = $ingestedLaw->metadata;

        $this->assertArrayHasKey('informator_enrichment', $metadata);
        $enrichment = $metadata['informator_enrichment'];

        $this->assertArrayHasKey('cross_references', $enrichment);
        $this->assertArrayHasKey('enriched_at', $enrichment);
        $this->assertCount(2, $enrichment['cross_references']);
        $this->assertEquals('12345', $enrichment['cross_references'][0]['id']);
        $this->assertEquals('67890', $enrichment['cross_references'][1]['id']);
    }

    public function test_enrich_handles_api_failures_gracefully(): void
    {
        $ingestedLaw = IngestedLaw::create([
            'title' => 'Zakon o radu',
            'law_number' => 'NN 93/2014',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => ['source' => 'NN'],
            'ingested_at' => now(),
        ]);

        $mockClient = Mockery::mock(InformatorClient::class);
        $mockClient->shouldReceive('fetchListingHtml')
            ->once()
            ->andThrow(new \RuntimeException('Informator API connection refused'));

        config(['services.informator.enrichment_enabled' => true]);

        $service = new InformatorEnrichmentService($mockClient);

        // Act: Should NOT throw
        $service->enrich($ingestedLaw);

        // Assert: Metadata has failure marker but no crash
        $ingestedLaw->refresh();
        $metadata = $ingestedLaw->metadata;

        $this->assertArrayHasKey('informator_enrichment', $metadata);
        $this->assertTrue($metadata['informator_enrichment']['failed']);
        $this->assertStringContainsString('connection refused', $metadata['informator_enrichment']['error']);
    }

    public function test_enrich_respects_feature_flag_when_disabled(): void
    {
        $ingestedLaw = IngestedLaw::create([
            'title' => 'Zakon o trgovackim drustvima',
            'law_number' => 'NN 111/1993',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => ['source' => 'NN'],
            'ingested_at' => now(),
        ]);

        $mockClient = Mockery::mock(InformatorClient::class);
        $mockClient->shouldNotReceive('fetchListingHtml');
        $mockClient->shouldNotReceive('parseListingForItems');

        config(['services.informator.enrichment_enabled' => false]);

        $service = new InformatorEnrichmentService($mockClient);

        $service->enrich($ingestedLaw);

        $ingestedLaw->refresh();
        $metadata = $ingestedLaw->metadata;
        $this->assertArrayNotHasKey('informator_enrichment', $metadata);
    }

    public function test_enrich_skips_when_already_enriched(): void
    {
        // Arrange: IngestedLaw already has enrichment data
        $ingestedLaw = IngestedLaw::create([
            'title' => 'Zakon o parnicnom postupku',
            'law_number' => 'NN 53/1991',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'metadata' => [
                'source' => 'NN',
                'informator_enrichment' => [
                    'cross_references' => [],
                    'enriched_at' => '2025-01-01T00:00:00+00:00',
                ],
            ],
            'ingested_at' => now(),
        ]);

        $mockClient = Mockery::mock(InformatorClient::class);
        $mockClient->shouldNotReceive('fetchListingHtml');

        config(['services.informator.enrichment_enabled' => true]);

        $service = new InformatorEnrichmentService($mockClient);

        // Act
        $service->enrich($ingestedLaw);

        // Assert: Metadata unchanged
        $ingestedLaw->refresh();
        $this->assertEquals('2025-01-01T00:00:00+00:00', $ingestedLaw->metadata['informator_enrichment']['enriched_at']);
    }
}
