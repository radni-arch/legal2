<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\File;
use Mockery;

class GraphSchemaApplyCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_fails_when_schema_file_not_found(): void
    {
        $this->artisan('graph:schema-apply', ['--schema-file' => '/nonexistent/file.cypher'])
            ->assertFailed()
            ->expectsOutput('Schema file not found: /nonexistent/file.cypher');
    }

    /** @test */
    public function it_extracts_constraint_and_index_statements(): void
    {
        $schemaContent = <<<CYPHER
// Some comment
CREATE CONSTRAINT test_id IF NOT EXISTS FOR (t:Test) REQUIRE t.id IS UNIQUE;

// Another comment
CREATE INDEX test_name_idx IF NOT EXISTS FOR (t:Test) ON (t.name);
CYPHER;

        $tempFile = tempnam(sys_get_temp_dir(), 'schema_test_');
        File::put($tempFile, $schemaContent);

        $graph = Mockery::mock(GraphDatabaseService::class);
        $graph->shouldReceive('run')->twice();
        $this->app->instance(GraphDatabaseService::class, $graph);

        $this->artisan('graph:schema-apply', ['--schema-file' => $tempFile])
            ->assertSuccessful();

        unlink($tempFile);
    }

    /** @test */
    public function it_shows_dry_run_without_executing(): void
    {
        $schemaContent = 'CREATE CONSTRAINT test_id IF NOT EXISTS FOR (t:Test) REQUIRE t.id IS UNIQUE;';

        $tempFile = tempnam(sys_get_temp_dir(), 'schema_test_');
        File::put($tempFile, $schemaContent);

        $graph = Mockery::mock(GraphDatabaseService::class);
        $graph->shouldNotReceive('run');
        $this->app->instance(GraphDatabaseService::class, $graph);

        $this->artisan('graph:schema-apply', [
            '--schema-file' => $tempFile,
            '--dry-run' => true
        ])
            ->assertSuccessful()
            ->expectsOutput('DRY RUN - No changes will be made');

        unlink($tempFile);
    }

    /** @test */
    public function it_handles_already_exists_errors_gracefully(): void
    {
        $schemaContent = 'CREATE CONSTRAINT test_id IF NOT EXISTS FOR (t:Test) REQUIRE t.id IS UNIQUE;';

        $tempFile = tempnam(sys_get_temp_dir(), 'schema_test_');
        File::put($tempFile, $schemaContent);

        $graph = Mockery::mock(GraphDatabaseService::class);
        $graph->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Constraint already exists'));
        $this->app->instance(GraphDatabaseService::class, $graph);

        // Should succeed even with "already exists" error
        $this->artisan('graph:schema-apply', ['--schema-file' => $tempFile])
            ->assertSuccessful();

        unlink($tempFile);
    }

    /** @test */
    public function it_reports_real_errors(): void
    {
        $schemaContent = 'CREATE CONSTRAINT test_id IF NOT EXISTS FOR (t:Test) REQUIRE t.id IS UNIQUE;';

        $tempFile = tempnam(sys_get_temp_dir(), 'schema_test_');
        File::put($tempFile, $schemaContent);

        $graph = Mockery::mock(GraphDatabaseService::class);
        $graph->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Connection refused'));
        $this->app->instance(GraphDatabaseService::class, $graph);

        $this->artisan('graph:schema-apply', ['--schema-file' => $tempFile])
            ->assertFailed();

        unlink($tempFile);
    }

    /** @test */
    public function it_warns_when_no_statements_found(): void
    {
        $schemaContent = '// Just comments, no statements';

        $tempFile = tempnam(sys_get_temp_dir(), 'schema_test_');
        File::put($tempFile, $schemaContent);

        $this->artisan('graph:schema-apply', ['--schema-file' => $tempFile])
            ->assertSuccessful()
            ->expectsOutput('No CREATE CONSTRAINT or CREATE INDEX statements found.');

        unlink($tempFile);
    }
}
