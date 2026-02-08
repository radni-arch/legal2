<?php

namespace Tests\Feature\Console;

use App\Services\Analysis\CaseLevel\DocumentIdentityBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $result = $this->getSampleResult();

        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($result);

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'test-case', '--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"total_identities": 11')
            ->expectsOutputToContain('"present": 8')
            ->expectsOutputToContain('"missing": 3');
    }

    /** @test */
    public function it_shows_human_readable_output_by_default(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'case-2025-001'])
            ->assertSuccessful()
            ->expectsOutputToContain('Completeness report: case-2025-001')
            ->expectsOutputToContain('Present: 8')
            ->expectsOutputToContain('Missing: 3')
            ->expectsOutputToContain('Total: 11');
    }

    /** @test */
    public function it_displays_case_numbers_with_status_indicators(): void
    {
        $mockBuilder = Mockery::mock(DocumentIdentityBuilder::class);
        $mockBuilder->shouldReceive('build')
            ->once()
            ->andReturn($this->getSampleResult());

        $this->app->instance(DocumentIdentityBuilder::class, $mockBuilder);

        $this->artisan('case:completeness', ['case_id' => 'test-case'])
            ->assertSuccessful()
            ->expectsOutputToContain('K-123/2025')
            ->expectsOutputToContain('main_criminal')
            ->expectsOutputToContain('(3/3)');
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
     * Sample result matching DocumentIdentityBuilder::build() return format.
     */
    private function getSampleResult(): array
    {
        return [
            'total_identities' => 11,
            'present' => 8,
            'missing' => 3,
            'by_case_number' => [
                'K-123/2025' => [
                    'prefix' => 'K',
                    'role' => 'main_criminal',
                    'institution' => 'Opcinski sud',
                    'total_documents' => 3,
                    'present' => 3,
                    'missing' => 0,
                    'suffixes' => [
                        1 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 1],
                        2 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 2],
                        3 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 3],
                    ],
                ],
                'Pp Prz-74/2025' => [
                    'prefix' => 'Pp Prz',
                    'role' => 'search_warrant',
                    'institution' => 'Sud (Osijek)',
                    'total_documents' => 3,
                    'present' => 2,
                    'missing' => 1,
                    'suffixes' => [
                        1 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 4],
                        2 => ['status' => 'missing', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => 'rjesenje o pretrazi (sud)', 'doc_id' => null],
                        3 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 5],
                    ],
                ],
                'Kv-89/2025' => [
                    'prefix' => 'Kv',
                    'role' => 'detention',
                    'institution' => 'Sud',
                    'total_documents' => 4,
                    'present' => 2,
                    'missing' => 2,
                    'suffixes' => [
                        1 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 6],
                        2 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 7],
                        3 => ['status' => 'missing', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => 'zalba obrane', 'doc_id' => null],
                        4 => ['status' => 'missing', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => 'odluka o zalbi', 'doc_id' => null],
                    ],
                ],
                'KP-DO-321/2025' => [
                    'prefix' => 'KP-DO',
                    'role' => 'prosecution',
                    'institution' => 'DO',
                    'total_documents' => 2,
                    'present' => 2,
                    'missing' => 0,
                    'suffixes' => [
                        1 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 8],
                        2 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 9],
                    ],
                ],
            ],
            'by_klasa' => [],
            'processing_time_seconds' => 0.123,
        ];
    }

    private function getSampleResultWithMissing(): array
    {
        return [
            'total_identities' => 3,
            'present' => 2,
            'missing' => 1,
            'by_case_number' => [
                'Pp Prz-74/2025' => [
                    'prefix' => 'Pp Prz',
                    'role' => 'search_warrant',
                    'institution' => 'Sud',
                    'total_documents' => 3,
                    'present' => 2,
                    'missing' => 1,
                    'suffixes' => [
                        1 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 1],
                        2 => ['status' => 'missing', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => 'rjesenje o pretrazi (sud)', 'doc_id' => null],
                        3 => ['status' => 'present', 'klasa' => null, 'urbroj' => null, 'date' => null, 'type' => null, 'doc_id' => 2],
                    ],
                ],
            ],
            'by_klasa' => [],
            'processing_time_seconds' => 0.05,
        ];
    }
}
