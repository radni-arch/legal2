<?php

namespace Tests\Unit\Models;

use App\Models\TextractBatch;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractBatchTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_uses_string_primary_key()
    {
        $batch = new TextractBatch(['id' => 'test-batch-id']);

        $this->assertFalse($batch->incrementing);
        $this->assertEquals('string', $batch->getKeyType());
    }

    /** @test */
    public function it_auto_generates_uuid_on_create()
    {
        $batch = TextractBatch::create([
            'batch_type' => 'pdf_extraction',
            'total_files' => 10,
        ]);

        $this->assertNotNull($batch->id);
        $this->assertTrue(Str::isUuid($batch->id));
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $batch = TextractBatch::create([
            'id' => Str::uuid()->toString(),
            'batch_type' => 'law_documents',
            'source_identifier' => 'law-batch-001',
            'total_files' => 100,
            'processed_files' => 50,
            'failed_files' => 5,
            'status' => 'processing',
            'configuration' => ['ocr' => true],
            'statistics' => ['avg_pages' => 15],
        ]);

        $this->assertEquals('law_documents', $batch->batch_type);
        $this->assertEquals(100, $batch->total_files);
        $this->assertEquals(50, $batch->processed_files);
    }

    /** @test */
    public function it_casts_arrays()
    {
        $batch = TextractBatch::create([
            'configuration' => ['ocr' => true, 'lang' => 'hr'],
            'statistics' => ['pages' => 500],
        ]);

        $this->assertIsArray($batch->configuration);
        $this->assertIsArray($batch->statistics);
        $this->assertTrue($batch->configuration['ocr']);
    }

    /** @test */
    public function it_calculates_progress()
    {
        $batch = TextractBatch::create([
            'total_files' => 100,
            'processed_files' => 75,
        ]);

        $this->assertEquals(75.0, $batch->progress);
    }

    /** @test */
    public function it_calculates_success_rate()
    {
        $batch = TextractBatch::create([
            'total_files' => 100,
            'processed_files' => 80,
            'failed_files' => 5,
        ]);

        $this->assertEquals(93.8, $batch->success_rate); // (80-5)/80 * 100
    }

    /** @test */
    public function it_increments_processed_count()
    {
        $batch = TextractBatch::create([
            'total_files' => 10,
            'processed_files' => 5,
            'failed_files' => 0,
        ]);

        $batch->incrementProcessed(false);

        $this->assertEquals(6, $batch->fresh()->processed_files);
        $this->assertEquals(0, $batch->fresh()->failed_files);
    }

    /** @test */
    public function it_marks_as_completed_when_all_processed()
    {
        $batch = TextractBatch::create([
            'total_files' => 10,
            'processed_files' => 9,
            'status' => 'processing',
        ]);

        $batch->incrementProcessed();

        $this->assertEquals('completed', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->completed_at);
    }

    /** @test */
    public function it_has_status_scopes()
    {
        TextractBatch::create(['status' => 'pending']);
        TextractBatch::create(['status' => 'processing']);
        TextractBatch::create(['status' => 'completed']);

        $this->assertCount(1, TextractBatch::pending()->get());
        $this->assertCount(1, TextractBatch::processing()->get());
        $this->assertCount(1, TextractBatch::completed()->get());
    }
}
