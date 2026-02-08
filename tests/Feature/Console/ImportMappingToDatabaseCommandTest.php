<?php

namespace Tests\Feature\Console;

use App\Services\CatalogService;
use Mockery;
use Tests\TestCase;

class ImportMappingToDatabaseCommandTest extends TestCase
{
    protected $catalogMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogMock = Mockery::mock(CatalogService::class);
        $this->app->instance(CatalogService::class, $this->catalogMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_fails_when_mapping_file_not_found()
    {
        $this->artisan('catalog:import-mapping', [
            '--mapping' => '/nonexistent/path/mapping.json',
            '--vs' => 'vs_test123',
        ])
            ->expectsOutputToContain('File not found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_shows_dry_run_preview()
    {
        // Create a temporary mapping file
        $tempDir = storage_path('app/test-mapping');
        @mkdir($tempDir, 0755, true);
        $mappingPath = $tempDir . '/mapping.json';

        $testData = [
            ['file_name' => 'doc1.pdf', 'file_id' => 'file_abc123', 'file_path' => '/path/to/doc1.pdf'],
            ['file_name' => 'doc2.pdf', 'file_id' => 'file_def456', 'file_path' => '/path/to/doc2.pdf'],
            ['file_name' => 'doc3.pdf', 'file_id' => null, 'file_path' => '/path/to/doc3.pdf'],
        ];
        file_put_contents($mappingPath, json_encode($testData));

        $this->artisan('catalog:import-mapping', [
            '--mapping' => $mappingPath,
            '--vs' => 'vs_test123',
            '--dry' => true,
        ])
            ->expectsOutputToContain('[DRY RUN] Would import 3 entries')
            ->expectsOutputToContain('doc1.pdf')
            ->expectsOutputToContain('vs_test123')
            ->assertExitCode(0);

        // Cleanup
        unlink($mappingPath);
        rmdir($tempDir);
    }

    /** @test */
    public function it_imports_mapping_data_to_database()
    {
        // Create a temporary mapping file
        $tempDir = storage_path('app/test-mapping');
        @mkdir($tempDir, 0755, true);
        $mappingPath = $tempDir . '/mapping.json';

        $testData = [
            ['file_name' => 'doc1.pdf', 'file_id' => 'file_abc123'],
            ['file_name' => 'doc2.pdf', 'file_id' => 'file_def456'],
        ];
        file_put_contents($mappingPath, json_encode($testData));

        // Mock the CatalogService to expect importFromMapping call
        $this->catalogMock
            ->shouldReceive('importFromMapping')
            ->once()
            ->with($mappingPath, 'vs_test123')
            ->andReturn(2);

        $this->artisan('catalog:import-mapping', [
            '--mapping' => $mappingPath,
            '--vs' => 'vs_test123',
        ])
            ->expectsOutputToContain('Importing from')
            ->expectsOutputToContain('Successfully imported 2 documents')
            ->assertExitCode(0);

        // Cleanup
        unlink($mappingPath);
        rmdir($tempDir);
    }

    /** @test */
    public function it_uses_default_mapping_path_when_not_provided()
    {
        // Create the default mapping location
        $defaultPath = storage_path('app/tagged/mappping.json');
        $defaultDir = dirname($defaultPath);
        @mkdir($defaultDir, 0755, true);

        $testData = [
            ['file_name' => 'default-doc.pdf', 'file_id' => 'file_xyz789'],
        ];
        file_put_contents($defaultPath, json_encode($testData));

        $this->catalogMock
            ->shouldReceive('importFromMapping')
            ->once()
            ->with($defaultPath, 'vs_default')
            ->andReturn(1);

        $this->artisan('catalog:import-mapping', [
            '--vs' => 'vs_default',
        ])
            ->expectsOutputToContain('Successfully imported 1 documents')
            ->assertExitCode(0);

        // Cleanup
        unlink($defaultPath);
    }

    /** @test */
    public function it_asks_for_vector_store_id_when_not_provided()
    {
        // Create a temporary mapping file
        $tempDir = storage_path('app/test-mapping');
        @mkdir($tempDir, 0755, true);
        $mappingPath = $tempDir . '/mapping.json';

        $testData = [['file_name' => 'doc.pdf', 'file_id' => 'file_123']];
        file_put_contents($mappingPath, json_encode($testData));

        $this->catalogMock
            ->shouldReceive('getDefaultVectorStoreId')
            ->once()
            ->andReturn('vs_default_from_service');

        $this->catalogMock
            ->shouldReceive('importFromMapping')
            ->once()
            ->with($mappingPath, 'vs_user_input')
            ->andReturn(1);

        $this->artisan('catalog:import-mapping', [
            '--mapping' => $mappingPath,
        ])
            ->expectsQuestion('Default Vector Store ID?', 'vs_user_input')
            ->expectsOutputToContain('Successfully imported 1 documents')
            ->assertExitCode(0);

        // Cleanup
        unlink($mappingPath);
        rmdir($tempDir);
    }

    /** @test */
    public function it_shows_sample_entries_in_dry_run()
    {
        $tempDir = storage_path('app/test-mapping');
        @mkdir($tempDir, 0755, true);
        $mappingPath = $tempDir . '/mapping.json';

        // Create more than 3 entries to test "and N more" message
        $testData = [
            ['file_name' => 'doc1.pdf', 'file_id' => 'file_1'],
            ['file_name' => 'doc2.pdf', 'file_id' => 'file_2'],
            ['file_name' => 'doc3.pdf', 'file_id' => 'file_3'],
            ['file_name' => 'doc4.pdf', 'file_id' => 'file_4'],
            ['file_name' => 'doc5.pdf', 'file_id' => 'file_5'],
        ];
        file_put_contents($mappingPath, json_encode($testData));

        $this->artisan('catalog:import-mapping', [
            '--mapping' => $mappingPath,
            '--vs' => 'vs_test',
            '--dry' => true,
        ])
            ->expectsOutputToContain('doc1.pdf')
            ->expectsOutputToContain('doc2.pdf')
            ->expectsOutputToContain('doc3.pdf')
            ->expectsOutputToContain('and 2 more')
            ->assertExitCode(0);

        // Cleanup
        unlink($mappingPath);
        rmdir($tempDir);
    }
}
