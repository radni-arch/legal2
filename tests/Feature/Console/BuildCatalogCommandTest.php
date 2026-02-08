<?php

namespace Tests\Feature\Console;

use App\Models\VectorDocument;
use App\Services\CatalogService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for BuildCatalog (catalog:build) command.
 *
 * Tests building and uploading catalog.json from database documents.
 */
class BuildCatalogCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $catalogServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogServiceMock = Mockery::mock(CatalogService::class);
        $this->app->instance(CatalogService::class, $this->catalogServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_displays_warning_when_no_documents_found()
    {
        $vsId = 'vs_test_123';

        // Create no documents - database is empty
        $this->artisan('catalog:build', ['vs' => $vsId])
            ->expectsOutput("No documents found for VS {$vsId}")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_builds_catalog_and_shows_result()
    {
        $vsId = 'vs_test_catalog';

        // Create test documents with proper status
        VectorDocument::factory()->count(3)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_UPLOADED,
        ]);

        $this->catalogServiceMock->shouldReceive('buildAndUploadCatalog')
            ->once()
            ->with($vsId, Mockery::any(), false)
            ->andReturn([
                'entries' => 3,
                'path' => '/tmp/catalog/vs_test_catalog/catalog.json',
                'file_id' => null,
            ]);

        $this->artisan('catalog:build', ['vs' => $vsId])
            ->expectsOutput("Building catalog for VS {$vsId} (3 documents)...")
            ->expectsOutput('Catalog built: 3 entries')
            ->expectsOutput('Saved to: /tmp/catalog/vs_test_catalog/catalog.json')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_dry_run_flag()
    {
        $vsId = 'vs_test_dry';

        // Create a test document
        $doc = VectorDocument::factory()->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_UPLOADED,
            'metadata' => [
                'case_id' => 'KP-123',
                'vrsta' => 'odluka',
            ],
        ]);

        $this->catalogServiceMock->shouldReceive('buildCatalogEntry')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof VectorDocument))
            ->andReturn([
                'type' => 'catalog_entry',
                'file_name' => $doc->file_name,
                'case_id' => 'KP-123',
            ]);

        $this->artisan('catalog:build', ['vs' => $vsId, '--dry' => true])
            ->expectsOutput("Building catalog for VS {$vsId} (1 documents)...")
            ->expectsOutputToContain('[DRY RUN] Sample catalog entries:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uploads_when_upload_flag_provided()
    {
        $vsId = 'vs_test_upload';

        VectorDocument::factory()->count(2)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_CATALOGED,
        ]);

        $this->catalogServiceMock->shouldReceive('buildAndUploadCatalog')
            ->once()
            ->with($vsId, Mockery::any(), true)
            ->andReturn([
                'entries' => 2,
                'path' => '/tmp/catalog/vs_test_upload/catalog.json',
                'file_id' => 'file-abc123',
            ]);

        $this->artisan('catalog:build', ['vs' => $vsId, '--upload' => true])
            ->expectsOutput("Building catalog for VS {$vsId} (2 documents)...")
            ->expectsOutput('Catalog built: 2 entries')
            ->expectsOutput('Uploaded as: file-abc123')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uses_custom_output_directory()
    {
        $vsId = 'vs_test_outdir';
        $customDir = '/custom/output/path';

        VectorDocument::factory()->count(1)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_UPLOADED,
        ]);

        $this->catalogServiceMock->shouldReceive('buildAndUploadCatalog')
            ->once()
            ->with($vsId, $customDir, false)
            ->andReturn([
                'entries' => 1,
                'path' => $customDir . '/vs_test_outdir/catalog.json',
                'file_id' => null,
            ]);

        $this->artisan('catalog:build', ['vs' => $vsId, '--out' => $customDir])
            ->expectsOutput('Catalog built: 1 entries')
            ->expectsOutput("Saved to: {$customDir}/vs_test_outdir/catalog.json")
            ->assertExitCode(0);
    }

    /** @test */
    public function it_includes_cataloged_documents_in_count()
    {
        $vsId = 'vs_test_mixed_status';

        // Create uploaded and cataloged documents (both should be included)
        VectorDocument::factory()->count(2)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_UPLOADED,
        ]);
        VectorDocument::factory()->count(3)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_CATALOGED,
        ]);
        // Pending documents should NOT be included
        VectorDocument::factory()->count(1)->create([
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_PENDING,
        ]);

        $this->catalogServiceMock->shouldReceive('buildAndUploadCatalog')
            ->once()
            ->andReturn([
                'entries' => 5,
                'path' => '/tmp/catalog/catalog.json',
                'file_id' => null,
            ]);

        // Should count 5 documents (2 uploaded + 3 cataloged), not 6
        $this->artisan('catalog:build', ['vs' => $vsId])
            ->expectsOutput("Building catalog for VS {$vsId} (5 documents)...")
            ->assertExitCode(0);
    }
}
