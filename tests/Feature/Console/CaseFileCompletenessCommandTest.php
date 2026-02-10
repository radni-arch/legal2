<?php

namespace Tests\Feature\Console;

use App\Services\Analysis\CaseLevel\DocumentIdentityBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class CaseFileCompletenessCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_case_id_argument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');

        $this->artisan('case:completeness');
    }

    /** @test */
    public function it_calls_builder_with_case_id(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->with('case-2025-001')
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'case-2025-001'])
            ->assertSuccessful();
    }

    /** @test */
    public function it_shows_rebuild_message_with_flag(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'test-case', '--rebuild' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Rebuilding document identity matrix...');
    }

    /** @test */
    public function it_outputs_json_with_json_flag(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        // Use Artisan::call to capture full output - expectsOutputToContain has
        // a known limitation where multiple substring checks against the same
        // output line only match the first registered expectation.
        Artisan::call('case:completeness', ['case_id' => 'test-case', '--json' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('"total_identities": 11', $output);
        $this->assertStringContainsString('"present": 8', $output);
        $this->assertStringContainsString('"missing": 3', $output);
    }

    /** @test */
    public function it_shows_human_readable_output_by_default(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        Artisan::call('case:completeness', ['case_id' => 'case-2025-001']);
        $output = Artisan::output();

        $this->assertStringContainsString('Completeness report: case-2025-001', $output);
        $this->assertStringContainsString('Present: 8', $output);
        $this->assertStringContainsString('Missing: 3', $output);
        $this->assertStringContainsString('Total: 11', $output);
    }

    /** @test */
    public function it_displays_case_numbers_with_status_indicators(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        Artisan::call('case:completeness', ['case_id' => 'test-case']);
        $output = Artisan::output();

        $this->assertStringContainsString('K-123/2025', $output);
        $this->assertStringContainsString('main_criminal', $output);
        $this->assertStringContainsString('(3/3)', $output);
    }

    /** @test */
    public function it_shows_missing_document_warnings(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResultWithMissing());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'test-case'])
            ->assertSuccessful()
            ->expectsOutputToContain('nedostaje');
    }

    /** @test */
    public function it_shows_processing_time(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'test-case'])
            ->assertSuccessful()
            ->expectsOutputToContain('Done in 0.123s');
    }

    /**
     * Sample result matching DocumentIdentityBuilder::build() flat return format.
     *
     * 8 present + 3 missing = 11 total identities.
     * Grouped by base case number:
     *   K-123/2025: 3 suffixes, all present (3/3)
     *   Pp Prz-74/2025: 3 suffixes, 2 present + 1 missing (2/3)
     *   Kv-89/2025: 4 suffixes, 2 present + 2 missing (2/4)
     *   KP-DO-321/2025: 1 suffix, present (1/1)
     */
    private function getSampleResult(): array
    {
        return [
            'total_identities' => 11,
            'present' => 8,
            'missing' => 3,
            'by_case_number' => [
                'K-123/2025-1' => [
                    'document_id' => 'doc-1', 'status' => 'present', 'suffix' => 1,
                    'base_case_number' => 'K-123/2025', 'role' => 'main_criminal',
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'K-123/2025-2' => [
                    'document_id' => 'doc-2', 'status' => 'present', 'suffix' => 2,
                    'base_case_number' => 'K-123/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'K-123/2025-3' => [
                    'document_id' => 'doc-3', 'status' => 'present', 'suffix' => 3,
                    'base_case_number' => 'K-123/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Pp Prz-74/2025-1' => [
                    'document_id' => 'doc-4', 'status' => 'present', 'suffix' => 1,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => 'search_warrant',
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Pp Prz-74/2025-2' => [
                    'document_id' => null, 'status' => 'missing', 'suffix' => 2,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                    'inference_reason' => 'inferred_from_gap',
                ],
                'Pp Prz-74/2025-3' => [
                    'document_id' => 'doc-5', 'status' => 'present', 'suffix' => 3,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Kv-89/2025-1' => [
                    'document_id' => 'doc-6', 'status' => 'present', 'suffix' => 1,
                    'base_case_number' => 'Kv-89/2025', 'role' => 'detention',
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Kv-89/2025-2' => [
                    'document_id' => 'doc-7', 'status' => 'present', 'suffix' => 2,
                    'base_case_number' => 'Kv-89/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Kv-89/2025-3' => [
                    'document_id' => null, 'status' => 'missing', 'suffix' => 3,
                    'base_case_number' => 'Kv-89/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                    'inference_reason' => 'inferred_from_gap',
                ],
                'Kv-89/2025-4' => [
                    'document_id' => null, 'status' => 'missing', 'suffix' => 4,
                    'base_case_number' => 'Kv-89/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                    'inference_reason' => 'inferred_from_gap',
                ],
                'KP-DO-321/2025-1' => [
                    'document_id' => 'doc-8', 'status' => 'present', 'suffix' => 1,
                    'base_case_number' => 'KP-DO-321/2025', 'role' => 'prosecution',
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
            ],
            'by_klasa' => [],
            'processing_time_seconds' => 0.123,
        ];
    }

    /**
     * Sample result with missing documents for warning display test.
     */
    private function getSampleResultWithMissing(): array
    {
        return [
            'total_identities' => 3,
            'present' => 2,
            'missing' => 1,
            'by_case_number' => [
                'Pp Prz-74/2025-1' => [
                    'document_id' => 'doc-1', 'status' => 'present', 'suffix' => 1,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => 'search_warrant',
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
                'Pp Prz-74/2025-2' => [
                    'document_id' => null, 'status' => 'missing', 'suffix' => 2,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                    'inference_reason' => 'inferred_from_gap',
                ],
                'Pp Prz-74/2025-3' => [
                    'document_id' => 'doc-2', 'status' => 'present', 'suffix' => 3,
                    'base_case_number' => 'Pp Prz-74/2025', 'role' => null,
                    'klasa' => null, 'urbroj' => null, 'institution' => null,
                ],
            ],
            'by_klasa' => [],
            'processing_time_seconds' => 0.05,
        ];
    }
}
