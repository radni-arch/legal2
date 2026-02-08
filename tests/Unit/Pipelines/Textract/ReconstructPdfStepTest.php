<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\ReconstructPdfV2;
use App\Models\TextractJob;
use App\Pipelines\Textract\ReconstructPdfStep;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ReconstructPdfStepTest extends TestCase
{
    use UsesTestDatabase;

    protected ReconstructPdfStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new ReconstructPdfStep;
    }

    /** @test */
    public function it_reconstructs_pdf_from_ocr_document()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $mockOcrDocument = (object) [
            'pages' => [
                (object) ['pageNumber' => 1, 'lines' => []],
            ],
        ];

        $mock = Mockery::mock('overload:'.ReconstructPdfV2::class);
        $mock->shouldReceive('run')
            ->once()
            ->with($mockOcrDocument, 'file-123')
            ->andReturn('/tmp/reconstructed.pdf');

        $payload = [
            'job' => $job,
            'ocrDocument' => $mockOcrDocument,
            'driveFileId' => 'file-123',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('targetLocalPath', $result);
        $this->assertEquals('/tmp/reconstructed.pdf', $result['targetLocalPath']);
    }

    /** @test */
    public function it_adds_target_local_path_to_payload()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $ocrDoc = (object) ['pages' => []];
        $reconstructedPath = '/path/to/reconstructed/searchable.pdf';

        $mock = Mockery::mock('overload:'.ReconstructPdfV2::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn($reconstructedPath);

        $payload = [
            'job' => $job,
            'ocrDocument' => $ocrDoc,
            'driveFileId' => 'test-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('targetLocalPath', $result);
        $this->assertEquals($reconstructedPath, $result['targetLocalPath']);
    }

    /** @test */
    public function it_uses_correct_parameters_for_reconstruction()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $specificOcrDoc = (object) [
            'pages' => [
                (object) ['pageNumber' => 1, 'lines' => [(object) ['text' => 'Test']]],
                (object) ['pageNumber' => 2, 'lines' => [(object) ['text' => 'Content']]],
            ],
        ];
        $driveId = 'specific-drive-file-789';

        $mock = Mockery::mock('overload:'.ReconstructPdfV2::class);
        $mock->shouldReceive('run')
            ->once()
            ->with($specificOcrDoc, $driveId)
            ->andReturn('/tmp/output.pdf');

        $payload = [
            'job' => $job,
            'ocrDocument' => $specificOcrDoc,
            'driveFileId' => $driveId,
        ];

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $mock = Mockery::mock('overload:'.ReconstructPdfV2::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/tmp/reconstructed.pdf');

        $payload = [
            'job' => $job,
            'ocrDocument' => (object) ['pages' => []],
            'driveFileId' => 'file-id',
            'driveFileName' => 'document.pdf',
            'blocks' => [],
            'qualityMetrics' => ['confidence' => 0.95],
            'customField' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customField', $result);
        $this->assertEquals('preserved', $result['customField']);
        $this->assertArrayHasKey('driveFileName', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertArrayHasKey('targetLocalPath', $result);
    }

    /** @test */
    public function it_preserves_job_instance_in_payload()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $mock = Mockery::mock('overload:'.ReconstructPdfV2::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/tmp/file.pdf');

        $payload = [
            'job' => $job,
            'ocrDocument' => (object) ['pages' => []],
            'driveFileId' => 'file-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('job', $result);
        $this->assertSame($job->id, $result['job']->id);
    }
}
