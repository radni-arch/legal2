<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\DownloadDriveFile;
use App\Models\TextractJob;
use App\Pipelines\Textract\DownloadDriveFileStep;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DownloadDriveFileStepTest extends TestCase
{
    use UsesTestDatabase;

    protected DownloadDriveFileStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new DownloadDriveFileStep;
    }

    /** @test */
    public function it_downloads_file_from_google_drive()
    {
        $job = TextractJob::factory()->queued()->create();

        $mock = Mockery::mock('overload:'.DownloadDriveFile::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('test-file-123')
            ->andReturn('/tmp/downloaded-file.pdf');

        $payload = [
            'job' => $job,
            'driveFileId' => 'test-file-123',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('localPath', $result);
        $this->assertEquals('/tmp/downloaded-file.pdf', $result['localPath']);
    }

    /** @test */
    public function it_updates_job_status_to_uploading()
    {
        $job = TextractJob::factory()->queued()->create();

        $mock = Mockery::mock('overload:'.DownloadDriveFile::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/tmp/test.pdf');

        $payload = [
            'job' => $job,
            'driveFileId' => 'test-file',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('uploading', $job->status);
    }

    /** @test */
    public function it_adds_local_path_to_payload()
    {
        $job = TextractJob::factory()->queued()->create();

        $mock = Mockery::mock('overload:'.DownloadDriveFile::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/path/to/file.pdf');

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('localPath', $result);
        $this->assertEquals('/path/to/file.pdf', $result['localPath']);
    }

    /** @test */
    public function it_passes_all_payload_fields_to_next_step()
    {
        $job = TextractJob::factory()->queued()->create();

        $mock = Mockery::mock('overload:'.DownloadDriveFile::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/tmp/file.pdf');

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'driveFileName' => 'document.pdf',
            'customField' => 'custom-value',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customField', $result);
        $this->assertEquals('custom-value', $result['customField']);
        $this->assertArrayHasKey('driveFileName', $result);
        $this->assertEquals('document.pdf', $result['driveFileName']);
    }

    /** @test */
    public function it_preserves_job_instance_in_payload()
    {
        $job = TextractJob::factory()->queued()->create();

        $mock = Mockery::mock('overload:'.DownloadDriveFile::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('/tmp/file.pdf');

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('job', $result);
        $this->assertSame($job->id, $result['job']->id);
    }
}
