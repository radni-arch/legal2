<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\UploadInputToS3;
use App\Models\TextractJob;
use App\Pipelines\Textract\UploadInputToS3Step;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UploadInputToS3StepTest extends TestCase
{
    use UsesTestDatabase;

    protected UploadInputToS3Step $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new UploadInputToS3Step;
    }

    /** @test */
    public function it_uploads_file_to_s3()
    {
        $job = TextractJob::factory()->queued()->create();

        $this->mock(UploadInputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->with('/tmp/test.pdf', 'file-123')
                ->andReturn('textract/input/abc123.pdf');
        });

        $payload = [
            'job' => $job,
            'localPath' => '/tmp/test.pdf',
            'driveFileId' => 'file-123',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('s3Key', $result);
        $this->assertEquals('textract/input/abc123.pdf', $result['s3Key']);
    }

    /** @test */
    public function it_updates_job_with_s3_key_and_started_status()
    {
        $job = TextractJob::factory()->queued()->create();

        $this->mock(UploadInputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('textract/input/test-key.pdf');
        });

        $payload = [
            'job' => $job,
            'localPath' => '/tmp/file.pdf',
            'driveFileId' => 'file-id',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('textract/input/test-key.pdf', $job->s3_key);
        $this->assertEquals('started', $job->status);
    }

    /** @test */
    public function it_adds_s3_key_to_payload()
    {
        $job = TextractJob::factory()->queued()->create();

        $this->mock(UploadInputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('textract/input/uploaded.pdf');
        });

        $payload = [
            'job' => $job,
            'localPath' => '/tmp/doc.pdf',
            'driveFileId' => 'drive-123',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('s3Key', $result);
        $this->assertEquals('textract/input/uploaded.pdf', $result['s3Key']);
    }

    /** @test */
    public function it_uses_correct_parameters_for_upload_action()
    {
        $job = TextractJob::factory()->queued()->create();

        $localPath = '/path/to/local/file.pdf';
        $driveFileId = 'google-drive-file-456';

        $this->mock(UploadInputToS3::class, function ($mock) use ($localPath, $driveFileId) {
            $mock->shouldReceive('handle')
                ->once()
                ->with($localPath, $driveFileId)
                ->andReturn('textract/input/result.pdf');
        });

        $payload = [
            'job' => $job,
            'localPath' => $localPath,
            'driveFileId' => $driveFileId,
        ];

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_passes_payload_to_next_step()
    {
        $job = TextractJob::factory()->queued()->create();

        $this->mock(UploadInputToS3::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn('textract/input/file.pdf');
        });

        $payload = [
            'job' => $job,
            'localPath' => '/tmp/test.pdf',
            'driveFileId' => 'file-id',
            'otherData' => 'preserved',
        ];

        $nextCalled = false;
        $nextPayload = null;

        $this->step->handle($payload, function ($p) use (&$nextCalled, &$nextPayload) {
            $nextCalled = true;
            $nextPayload = $p;

            return $p;
        });

        $this->assertTrue($nextCalled);
        $this->assertArrayHasKey('otherData', $nextPayload);
        $this->assertEquals('preserved', $nextPayload['otherData']);
        $this->assertArrayHasKey('s3Key', $nextPayload);
    }
}
