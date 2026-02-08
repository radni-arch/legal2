<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\UploadOutputToS3;
use App\Models\TextractJob;
use App\Pipelines\Textract\UploadOutputStep;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UploadOutputStepTest extends TestCase
{
    use UsesTestDatabase;

    protected UploadOutputStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new UploadOutputStep;
    }

    /** @test */
    public function it_uploads_reconstructed_pdf_to_s3()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $this->mock(UploadOutputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->with('file-123', '/tmp/reconstructed.pdf')
                ->andReturn('textract/output/file-123.pdf');
        });

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-123',
            'targetLocalPath' => '/tmp/reconstructed.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('outKey', $result);
        $this->assertEquals('textract/output/file-123.pdf', $result['outKey']);
    }

    /** @test */
    public function it_adds_out_key_to_payload()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $outKey = 'textract/output/searchable-document.pdf';

        $this->mock(UploadOutputToS3::class, function ($mock) use ($outKey) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn($outKey);
        });

        $payload = [
            'job' => $job,
            'driveFileId' => 'test-id',
            'targetLocalPath' => '/path/to/file.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('outKey', $result);
        $this->assertEquals($outKey, $result['outKey']);
    }

    /** @test */
    public function it_uses_correct_parameters_for_upload()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $driveFileId = 'specific-drive-file-789';
        $localPath = '/specific/path/to/reconstructed.pdf';

        $this->mock(UploadOutputToS3::class, function ($mock) use ($driveFileId, $localPath) {
            $mock->shouldReceive('handle')
                ->once()
                ->with($driveFileId, $localPath)
                ->andReturn('textract/output/result.pdf');
        });

        $payload = [
            'job' => $job,
            'driveFileId' => $driveFileId,
            'targetLocalPath' => $localPath,
        ];

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $this->mock(UploadOutputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('textract/output/file.pdf');
        });

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'targetLocalPath' => '/tmp/file.pdf',
            'ocrDocument' => (object) ['pages' => []],
            'qualityMetrics' => ['confidence' => 0.95],
            'customField' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customField', $result);
        $this->assertEquals('preserved', $result['customField']);
        $this->assertArrayHasKey('ocrDocument', $result);
        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertArrayHasKey('outKey', $result);
    }

    /** @test */
    public function it_preserves_job_instance_in_payload()
    {
        $job = TextractJob::factory()->create(['status' => 'reconstructing']);

        $this->mock(UploadOutputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('textract/output/file.pdf');
        });

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'targetLocalPath' => '/tmp/file.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('job', $result);
        $this->assertSame($job->id, $result['job']->id);
    }
}
