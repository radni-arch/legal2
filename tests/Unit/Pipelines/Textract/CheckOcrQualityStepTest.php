<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Models\TextractJob;
use App\Pipelines\Textract\CheckOcrQualityStep;
use App\Services\Ocr\OcrQualityAnalyzer;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CheckOcrQualityStepTest extends TestCase
{
    use UsesTestDatabase;

    protected CheckOcrQualityStep $step;

    protected $qualityAnalyzerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qualityAnalyzerMock = Mockery::mock(OcrQualityAnalyzer::class);
        $this->step = new CheckOcrQualityStep($this->qualityAnalyzerMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_analyzes_ocr_quality_from_blocks()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $blocks = [
            ['BlockType' => 'PAGE', 'Confidence' => 95],
            ['BlockType' => 'LINE', 'Confidence' => 90],
        ];

        $qualityMetrics = [
            'confidence' => 0.92,
            'coverage' => 0.85,
            'low_confidence_pages' => 0,
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->with($blocks)
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => $blocks,
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertEquals($qualityMetrics, $result['qualityMetrics']);
    }

    /** @test */
    public function it_determines_document_needs_review_for_low_confidence()
    {
        config(['vizra-adk.ocr.min_confidence' => 0.85]);
        config(['vizra-adk.ocr.min_coverage' => 0.75]);

        $job = TextractJob::factory()->analyzing()->create();

        $qualityMetrics = [
            'confidence' => 0.70, // Below threshold
            'coverage' => 0.85,
            'low_confidence_pages' => 0,
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('needsReview', $result);
        $this->assertTrue($result['needsReview']);
        $this->assertArrayHasKey('reviewReasons', $result);
        $this->assertNotEmpty($result['reviewReasons']);
        $this->assertStringContainsString('Low overall confidence', $result['reviewReasons'][0]);
    }

    /** @test */
    public function it_determines_document_needs_review_for_low_coverage()
    {
        config(['vizra-adk.ocr.min_confidence' => 0.85]);
        config(['vizra-adk.ocr.min_coverage' => 0.80]);

        $job = TextractJob::factory()->analyzing()->create();

        $qualityMetrics = [
            'confidence' => 0.90,
            'coverage' => 0.60, // Below threshold
            'low_confidence_pages' => 0,
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertTrue($result['needsReview']);
        $this->assertStringContainsString('Low coverage', $result['reviewReasons'][0]);
    }

    /** @test */
    public function it_determines_document_needs_review_for_too_many_low_confidence_pages()
    {
        config(['vizra-adk.ocr.max_low_confidence_pages' => 3]);

        $job = TextractJob::factory()->analyzing()->create();

        $qualityMetrics = [
            'confidence' => 0.90,
            'coverage' => 0.85,
            'low_confidence_pages' => 5, // Above threshold
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertTrue($result['needsReview']);
        $this->assertStringContainsString('Too many low-confidence pages', $result['reviewReasons'][0]);
    }

    /** @test */
    public function it_does_not_flag_for_review_when_quality_is_good()
    {
        config(['vizra-adk.ocr.min_confidence' => 0.82]);
        config(['vizra-adk.ocr.min_coverage' => 0.75]);
        config(['vizra-adk.ocr.max_low_confidence_pages' => 3]);

        $job = TextractJob::factory()->analyzing()->create();

        $qualityMetrics = [
            'confidence' => 0.95, // Good
            'coverage' => 0.92, // Good
            'low_confidence_pages' => 1, // Good
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertFalse($result['needsReview']);
        $this->assertEmpty($result['reviewReasons']);
    }

    /** @test */
    public function it_updates_job_metadata_with_quality_metrics()
    {
        $job = TextractJob::factory()->analyzing()->create([
            'metadata' => ['existing' => 'data'],
        ]);

        $qualityMetrics = [
            'confidence' => 0.88,
            'coverage' => 0.82,
            'low_confidence_pages' => 2,
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertArrayHasKey('ocrQuality', $job->metadata);
        $this->assertEquals($qualityMetrics, $job->metadata['ocrQuality']);
        $this->assertArrayHasKey('needsReview', $job->metadata);
        $this->assertArrayHasKey('existing', $job->metadata);
        $this->assertEquals('data', $job->metadata['existing']);
    }

    /** @test */
    public function it_handles_empty_blocks_array()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $qualityMetrics = [
            'confidence' => 0.0,
            'coverage' => 0.0,
            'low_confidence_pages' => 0,
        ];

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->with([])
            ->andReturn($qualityMetrics);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('qualityMetrics', $result);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->qualityAnalyzerMock
            ->shouldReceive('analyzeFromBlocks')
            ->once()
            ->andReturn([
                'confidence' => 0.90,
                'coverage' => 0.85,
                'low_confidence_pages' => 1,
            ]);

        $payload = [
            'job' => $job,
            'blocks' => [],
            'driveFileId' => 'file-123',
            'driveFileName' => 'test.pdf',
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
        $this->assertArrayHasKey('qualityMetrics', $result);
        $this->assertArrayHasKey('needsReview', $result);
    }
}
