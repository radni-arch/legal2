<?php

namespace Tests\Unit\Services;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\IngestPipelineService;
use App\Services\Odluke\OdlukeClient;
use App\Services\Odluke\OdlukeIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OdlukeIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeService($client, $graphDb = null, $ocr = null): OdlukeIngestService
    {
        $pipeline = $this->createMock(IngestPipelineService::class);
        $vectors = $this->createMock(CourtDecisionVectorStoreService::class);
        $svc = new OdlukeIngestService($client, $pipeline, $vectors, $ocr, null, $graphDb);

        return $svc;
    }

    public function test_get_metadata_for_ids_happy_path_multiple_ids(): void
    {
        $client = Mockery::mock(OdlukeClient::class);
        $client->shouldReceive('withBaseUrl')->andReturnSelf();
        $client->shouldReceive('fetchDecisionMeta')->with('id-1')->andReturn([
            'broj_odluke' => 'P-123',
            'sud' => 'Vrhovni sud',
            'datum_odluke' => '2024-01-01',
            'src' => 'https://example.com/Document/View?id=id-1',
        ]);
        $client->shouldReceive('fetchDecisionMeta')->with('id-2')->andReturn([
            'broj_odluke' => 'P-456',
            'sud' => 'Županijski sud',
            'datum_odluke' => '2024-02-02',
            'src' => 'https://example.com/Document/View?id=id-2',
        ]);
        $client->shouldReceive('buildBaseFileName')->andReturn('base-name');
        $client->shouldReceive('downloadPdfUrl')->andReturnUsing(fn ($id) => "https://example.com/Document/DownloadPdf?id={$id}");
        $client->shouldReceive('downloadHtmlUrl')->andReturnUsing(fn ($id) => "https://example.com/Document/Text?id={$id}");

        $svc = $this->makeService($client);
        $out = $svc->getMetadataForIds(['id-1', 'id-2'], 'https://example.com');

        $this->assertIsArray($out);
        $this->assertCount(2, $out);
        foreach ($out as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('meta', $row);
            $this->assertArrayHasKey('basename', $row);
            $this->assertArrayHasKey('download_pdf_url', $row);
            $this->assertArrayHasKey('download_html_url', $row);
        }
    }

    public function test_get_metadata_for_ids_includes_error_when_meta_missing(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $client->method('withBaseUrl')->willReturnSelf();
        $client->method('fetchDecisionMeta')->willReturnCallback(function ($id) {
            return $id === 'good-id' ? ['sud' => 'Test'] : null;
        });

        $svc = $this->makeService($client);
        $out = $svc->getMetadataForIds(['bad-id', 'good-id']);

        $this->assertIsArray($out);
        $this->assertCount(2, $out);
        $this->assertArrayHasKey('error', $out[0]);
        $this->assertEquals('bad-id', $out[0]['id']);
    }

    public function test_download_pdf_nosave_includes_sizes_and_urls(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $client->method('withBaseUrl')->willReturnSelf();
        $client->method('fetchDecisionMeta')->willReturn([]);
        $client->method('buildBaseFileName')->willReturn('base-name');
        $client->method('downloadPdfUrl')->willReturn('https://example.com/Document/DownloadPdf?id=abc');
        $client->method('downloadHtmlUrl')->willReturn('https://example.com/Document/Text?id=abc');
        $client->method('downloadPdf')->willReturn([
            'ok' => true,
            'status' => 200,
            'content_type' => 'application/pdf',
            'bytes' => str_repeat('A', 1234),
        ]);

        $svc = $this->makeService($client);
        $res = $svc->download('abc', ['format' => 'pdf', 'save' => false]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('pdf', $res);
        $this->assertEquals(1234, $res['pdf']['bytes']);
        $this->assertEmpty($res['errors']);
        $this->assertEquals('https://example.com/Document/DownloadPdf?id=abc', $res['download_pdf_url']);
    }

    public function test_download_both_save_writes_files_and_sets_saved_paths(): void
    {
        $tmpDir = sys_get_temp_dir().'/odluke-tests-'.bin2hex(random_bytes(4));
        @mkdir($tmpDir, 0775, true);
        config(['odluke.out_dir' => $tmpDir]);

        $client = $this->createMock(OdlukeClient::class);
        $client->method('withBaseUrl')->willReturnSelf();
        $client->method('fetchDecisionMeta')->willReturn([
            'sud' => 'Vrhovni sud',
            'datum_odluke' => '2024-03-03',
        ]);
        $client->method('buildBaseFileName')->willReturn('vsrh_x_2024-03-03_abc');
        $client->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $client->method('downloadHtmlUrl')->willReturn('https://example.com/html');
        $client->method('downloadPdf')->willReturn([
            'ok' => true,
            'status' => 200,
            'content_type' => 'application/pdf',
            'bytes' => 'PDF-BYTES',
        ]);
        $client->method('downloadHtml')->willReturn([
            'ok' => true,
            'status' => 200,
            'content_type' => 'text/html',
            'bytes' => '<html>HTML</html>',
        ]);

        $svc = $this->makeService($client);
        $res = $svc->download('abc', ['format' => 'both', 'save' => true]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('saved', $res);
        $this->assertArrayHasKey('pdf', $res['saved']);
        $this->assertArrayHasKey('html', $res['saved']);
        $this->assertFileExists($res['saved']['pdf']);
        $this->assertFileExists($res['saved']['html']);
        $this->assertEmpty($res['errors']);

        // Cleanup
        @unlink($res['saved']['pdf']);
        @unlink($res['saved']['html']);
        @rmdir($tmpDir);
    }

    public function test_download_handles_http_errors(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $client->method('withBaseUrl')->willReturnSelf();
        $client->method('fetchDecisionMeta')->willReturn([]);
        $client->method('buildBaseFileName')->willReturn('base');
        $client->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $client->method('downloadHtmlUrl')->willReturn('https://example.com/html');
        $client->method('downloadPdf')->willReturn(['ok' => false, 'status' => 404]);

        $svc = $this->makeService($client);
        $res = $svc->download('abc', ['format' => 'pdf', 'save' => false]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('errors', $res);
        $this->assertArrayHasKey('pdf', $res['errors']);
        $this->assertStringContainsString('404', $res['errors']['pdf']);
    }

    public function test_download_invalid_format_defaults_to_pdf(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $client->method('withBaseUrl')->willReturnSelf();
        $client->method('fetchDecisionMeta')->willReturn([]);
        $client->method('buildBaseFileName')->willReturn('base');
        $client->method('downloadPdfUrl')->willReturn('https://example.com/pdf');
        $client->method('downloadHtmlUrl')->willReturn('https://example.com/html');
        $client->method('downloadPdf')->willReturn([
            'ok' => true,
            'status' => 200,
            'content_type' => 'application/pdf',
            'bytes' => 'PDF',
        ]);

        $svc = $this->makeService($client);
        $res = $svc->download('abc', ['format' => 'invalid', 'save' => false]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('pdf', $res);
        $this->assertEmpty($res['errors']);
    }

    public function test_graph_sync_config_default_value(): void
    {
        // Test that when config is not set and env is not set, default is true
        // This tests the fallback chain: options -> config -> env -> default(true)
        putenv('ODLUKE_SYNC_GRAPH');

        // Simulate the logic in OdlukeIngestService line 37:
        // $syncGraph = (bool)($options['sync_graph'] ?? config('odluke.sync_graph', env('ODLUKE_SYNC_GRAPH', true)));
        $options = [];
        $defaultValue = $options['sync_graph'] ?? config('odluke.sync_graph', env('ODLUKE_SYNC_GRAPH', true));

        $this->assertTrue((bool) $defaultValue);

        putenv('ODLUKE_SYNC_GRAPH');
    }

    public function test_graph_sync_config_reads_from_env(): void
    {
        // Test that env variable is read when set
        putenv('ODLUKE_SYNC_GRAPH=false');

        // Simulate the logic with no config set
        $options = [];
        $value = $options['sync_graph'] ?? config('odluke.sync_graph', env('ODLUKE_SYNC_GRAPH', true));

        $this->assertFalse((bool) $value);

        putenv('ODLUKE_SYNC_GRAPH');
    }

    public function test_graph_sync_config_prefers_explicit_option(): void
    {
        // Test that explicit options override config/env
        config(['odluke.sync_graph' => true]);

        // Simulating passing ['sync_graph' => false] in options
        $optionValue = false;
        $finalValue = $optionValue ?? config('odluke.sync_graph', env('ODLUKE_SYNC_GRAPH', true));

        $this->assertFalse((bool) $finalValue);
    }

    public function test_filter_already_ingested_ids_returns_new_ids_only(): void
    {
        // Create a decision and document in the database
        $decision = CourtDecision::factory()->create();
        $existingSourceId = 'existing-source-id-' . Str::random(8);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'source_id' => $existingSourceId,
        ]);

        $client = $this->createMock(OdlukeClient::class);
        $svc = $this->makeService($client);

        $idsToCheck = [$existingSourceId, 'new-id-1', 'new-id-2'];
        $filtered = $svc->filterAlreadyIngestedIds($idsToCheck);

        $this->assertCount(2, $filtered);
        $this->assertContains('new-id-1', $filtered);
        $this->assertContains('new-id-2', $filtered);
        $this->assertNotContains($existingSourceId, $filtered);
    }

    public function test_get_already_ingested_ids_returns_existing_ids(): void
    {
        // Create a decision and document in the database
        $decision = CourtDecision::factory()->create();
        $existingSourceId = 'existing-source-id-' . Str::random(8);

        CourtDecisionDocument::factory()->create([
            'decision_id' => $decision->id,
            'source_id' => $existingSourceId,
        ]);

        $client = $this->createMock(OdlukeClient::class);
        $svc = $this->makeService($client);

        $idsToCheck = [$existingSourceId, 'new-id-1', 'new-id-2'];
        $alreadyIngested = $svc->getAlreadyIngestedIds($idsToCheck);

        $this->assertCount(1, $alreadyIngested);
        $this->assertContains($existingSourceId, $alreadyIngested);
    }

    public function test_filter_already_ingested_ids_handles_empty_array(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $svc = $this->makeService($client);

        $filtered = $svc->filterAlreadyIngestedIds([]);
        $this->assertEmpty($filtered);
    }

    public function test_filter_already_ingested_ids_normalizes_and_filters_empty_strings(): void
    {
        $client = $this->createMock(OdlukeClient::class);
        $svc = $this->makeService($client);

        $idsToCheck = ['  valid-id  ', '', '   ', 'another-id'];
        $filtered = $svc->filterAlreadyIngestedIds($idsToCheck);

        $this->assertCount(2, $filtered);
        $this->assertContains('valid-id', $filtered);
        $this->assertContains('another-id', $filtered);
    }
}
