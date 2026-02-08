<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\SaveAnalysisResults;
use App\Models\TextractJob;
use App\Pipelines\Textract\SaveResultsStep;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SaveResultsStepTest extends TestCase
{
    use UsesTestDatabase;

    protected SaveResultsStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new SaveResultsStep;
    }

    /** @test */
    public function it_saves_analysis_results_to_s3_and_local()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $blocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1'],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'Test'],
        ];

        $resultsMeta = [
            's3JsonKey' => 'textract/output/results.json',
            'localJsonAbs' => '/tmp/textract/results.json',
            'localJsonRel' => 'textract/results.json',
        ];

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('file-123', $blocks)
            ->andReturn($resultsMeta);

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-123',
            'blocks' => $blocks,
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('resultsMeta', $result);
        $this->assertEquals($resultsMeta, $result['resultsMeta']);
    }

    /** @test */
    public function it_adds_results_meta_to_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $meta = [
            's3JsonKey' => 'textract/output/test.json',
            'localJsonAbs' => '/tmp/test.json',
        ];

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn($meta);

        $payload = [
            'job' => $job,
            'driveFileId' => 'test-id',
            'blocks' => [],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('resultsMeta', $result);
        $this->assertArrayHasKey('s3JsonKey', $result['resultsMeta']);
        $this->assertArrayHasKey('localJsonAbs', $result['resultsMeta']);
    }

    /** @test */
    public function it_passes_drive_file_id_and_blocks_correctly()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $driveFileId = 'specific-file-456';
        $blocks = [
            ['BlockType' => 'PAGE'],
            ['BlockType' => 'LINE', 'Text' => 'Specific content'],
        ];

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->with($driveFileId, $blocks)
            ->andReturn(['s3JsonKey' => 'test.json']);

        $payload = [
            'job' => $job,
            'driveFileId' => $driveFileId,
            'blocks' => $blocks,
        ];

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(['s3JsonKey' => 'test.json']);

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'blocks' => [],
            'jobId' => 'textract-job-123',
            's3Key' => 'textract/input/file.pdf',
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('jobId', $result);
        $this->assertArrayHasKey('s3Key', $result);
        $this->assertArrayHasKey('resultsMeta', $result);
    }

    /** @test */
    public function it_preserves_job_instance()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(['s3JsonKey' => 'test.json']);

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'blocks' => [],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('job', $result);
        $this->assertSame($job->id, $result['job']->id);
    }

    /** @test */
    public function it_handles_empty_blocks_array()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $mock = Mockery::mock('overload:'.SaveAnalysisResults::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('file-id', [])
            ->andReturn(['s3JsonKey' => 'empty.json']);

        $payload = [
            'job' => $job,
            'driveFileId' => 'file-id',
            'blocks' => [],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('resultsMeta', $result);
    }
}
