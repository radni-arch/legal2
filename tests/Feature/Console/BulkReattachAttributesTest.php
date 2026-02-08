<?php

namespace Tests\Feature\Console;

use App\Models\VectorDocument;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BulkReattachAttributesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_shows_message_when_no_documents_need_upload(): void
    {
        // No documents in DB that need upload

        $this->artisan('vs:bulk-reattach')
            ->expectsOutput('No documents need upload.')
            ->assertSuccessful();
    }

    public function test_it_processes_documents_needing_upload(): void
    {
        // Create documents needing upload (status=tagged, no openai_file_id)
        $doc = VectorDocument::create([
            'file_name' => 'test-doc.pdf',
            'file_path' => storage_path('app/test/test-doc.pdf'),
            'vector_store_id' => 'vs_test123',
            'status' => VectorDocument::STATUS_TAGGED,
            'metadata' => ['case_id' => 'CASE-001'],
        ]);

        // Mock CatalogService to verify uploadAndAttach is called
        $mockCatalog = Mockery::mock(CatalogService::class);
        $mockCatalog->shouldReceive('uploadAndAttach')
            ->once()
            ->with(Mockery::on(fn ($d) => $d->id === $doc->id))
            ->andReturn($doc);

        $this->app->instance(CatalogService::class, $mockCatalog);

        $this->artisan('vs:bulk-reattach')
            ->assertSuccessful();
    }

    public function test_it_filters_by_vector_store_when_vs_option_provided(): void
    {
        // Create documents in different stores
        $docStore1 = VectorDocument::create([
            'file_name' => 'doc-store1.pdf',
            'file_path' => storage_path('app/test/doc-store1.pdf'),
            'vector_store_id' => 'vs_store1',
            'status' => VectorDocument::STATUS_TAGGED,
            'metadata' => ['case_id' => 'CASE-001'],
        ]);

        $docStore2 = VectorDocument::create([
            'file_name' => 'doc-store2.pdf',
            'file_path' => storage_path('app/test/doc-store2.pdf'),
            'vector_store_id' => 'vs_store2',
            'status' => VectorDocument::STATUS_TAGGED,
            'metadata' => ['case_id' => 'CASE-002'],
        ]);

        // Mock CatalogService - should only be called for store1 doc
        $mockCatalog = Mockery::mock(CatalogService::class);
        $mockCatalog->shouldReceive('uploadAndAttach')
            ->once()
            ->with(Mockery::on(fn ($d) => $d->id === $docStore1->id))
            ->andReturn($docStore1);

        $this->app->instance(CatalogService::class, $mockCatalog);

        $this->artisan('vs:bulk-reattach', ['--vs' => 'vs_store1'])
            ->assertSuccessful();
    }

    public function test_it_shows_dry_run_output_when_dry_option_provided(): void
    {
        $doc = VectorDocument::create([
            'file_name' => 'dry-test.pdf',
            'file_path' => storage_path('app/test/dry-test.pdf'),
            'vector_store_id' => 'vs_test123',
            'status' => VectorDocument::STATUS_TAGGED,
            'metadata' => ['case_id' => 'CASE-001'],
        ]);

        // Mock CatalogService - should NOT be called in dry mode
        $mockCatalog = Mockery::mock(CatalogService::class);
        $mockCatalog->shouldNotReceive('uploadAndAttach');

        $this->app->instance(CatalogService::class, $mockCatalog);

        $this->artisan('vs:bulk-reattach', ['--dry' => true])
            ->expectsOutputToContain('[DRY]')
            ->assertSuccessful();
    }

    public function test_it_handles_upload_errors_gracefully(): void
    {
        $doc = VectorDocument::create([
            'file_name' => 'error-test.pdf',
            'file_path' => storage_path('app/test/error-test.pdf'),
            'vector_store_id' => 'vs_test123',
            'status' => VectorDocument::STATUS_TAGGED,
            'metadata' => ['case_id' => 'CASE-001'],
        ]);

        // Mock CatalogService to throw exception
        $mockCatalog = Mockery::mock(CatalogService::class);
        $mockCatalog->shouldReceive('uploadAndAttach')
            ->once()
            ->andThrow(new \Exception('API rate limit exceeded'));

        $this->app->instance(CatalogService::class, $mockCatalog);

        $this->artisan('vs:bulk-reattach')
            ->expectsOutputToContain('Failed: error-test.pdf')
            ->assertSuccessful();
    }
}
