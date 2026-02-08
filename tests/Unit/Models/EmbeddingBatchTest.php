<?php

namespace Tests\Unit\Models;

use App\Models\EmbeddingBatch;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EmbeddingBatchTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $batch = EmbeddingBatch::create([
            'source_type' => 'laws',
            'total_items' => 100,
            'processed_items' => 0,
            'failed_items' => 0,
            'status' => 'pending',
            'embedding_model' => 'text-embedding-3-small',
            'item_ids' => ['id1', 'id2', 'id3'],
            'configuration' => ['batch_size' => 20],
        ]);

        $this->assertEquals('laws', $batch->source_type);
        $this->assertEquals(100, $batch->total_items);
        $this->assertEquals('pending', $batch->status);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $batch = EmbeddingBatch::factory()->create();

        // Laravel 11+ returns LazyUuidFromString objects, cast to string for regex assertion
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $batch->id);
        $this->assertFalse($batch->incrementing);
        $this->assertEquals('string', $batch->getKeyType());
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $batch = EmbeddingBatch::factory()->create(['id' => null]);

        $this->assertNotNull($batch->id);
        // Verify UUID format (Laravel 11+ returns LazyUuidFromString)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $batch->id);
    }

    /** @test */
    public function it_casts_item_ids_as_array()
    {
        $itemIds = ['law-1', 'law-2', 'law-3', 'law-4'];
        $batch = EmbeddingBatch::factory()->create(['item_ids' => $itemIds]);

        $this->assertIsArray($batch->item_ids);
        $this->assertCount(4, $batch->item_ids);
        $this->assertContains('law-1', $batch->item_ids);
    }

    /** @test */
    public function it_casts_configuration_as_array()
    {
        $config = [
            'batch_size' => 20,
            'timeout' => 300,
            'retry_attempts' => 3,
        ];

        $batch = EmbeddingBatch::factory()->create(['configuration' => $config]);

        $this->assertIsArray($batch->configuration);
        $this->assertEquals(20, $batch->configuration['batch_size']);
    }

    /** @test */
    public function it_casts_numeric_fields_correctly()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 50,
            'processed_items' => 25,
            'failed_items' => 2,
            'tokens_used' => 10000,
        ]);

        $this->assertIsInt($batch->total_items);
        $this->assertIsInt($batch->processed_items);
        $this->assertIsInt($batch->failed_items);
        $this->assertIsInt($batch->tokens_used);
    }

    /** @test */
    public function it_casts_cost_as_decimal()
    {
        $batch = EmbeddingBatch::factory()->create(['cost' => '1.2500']);

        // Increased to 8 decimal places for precise cost tracking ($0.00002 per 1K tokens)
        $this->assertEquals('1.25000000', $batch->cost);
    }

    /** @test */
    public function it_casts_datetime_fields()
    {
        $batch = EmbeddingBatch::factory()->create([
            'started_at' => now(),
            'completed_at' => now()->addHour(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $batch->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $batch->completed_at);
    }

    /** @test */
    public function it_calculates_progress_percentage()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 100,
            'processed_items' => 50,
        ]);

        $this->assertEquals(50.0, $batch->progress);
    }

    /** @test */
    public function it_returns_zero_progress_when_total_is_zero()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 0,
            'processed_items' => 0,
        ]);

        $this->assertEquals(0, $batch->progress);
    }

    /** @test */
    public function it_returns_100_percent_when_all_processed()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 50,
            'processed_items' => 50,
        ]);

        $this->assertEquals(100.0, $batch->progress);
    }

    /** @test */
    public function it_increments_processed_count()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 10,
            'processed_items' => 0,
            'tokens_used' => 0,
            'cost' => '0.0000',
        ]);

        $batch->incrementProcessed(1000);

        $batch->refresh();

        $this->assertEquals(1, $batch->processed_items);
        $this->assertEquals(1000, $batch->tokens_used);
        $this->assertGreaterThan(0, (float) $batch->cost);
    }

    /** @test */
    public function it_increments_failed_count_when_failed_flag_is_true()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 10,
            'processed_items' => 0,
            'failed_items' => 0,
        ]);

        $batch->incrementProcessed(1000, true);

        $batch->refresh();

        $this->assertEquals(1, $batch->processed_items);
        $this->assertEquals(1, $batch->failed_items);
    }

    /** @test */
    public function it_calculates_cost_correctly()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 10,
            'tokens_used' => 0,
            'cost' => '0.0000',
        ]);

        // $0.00002 per 1K tokens
        $batch->incrementProcessed(1000); // Should add $0.00002

        $batch->refresh();

        $this->assertEquals(1000, $batch->tokens_used);
        $this->assertEqualsWithDelta(0.00002, (float) $batch->cost, 0.0000001);
    }

    /** @test */
    public function it_marks_as_completed_when_all_items_processed()
    {
        $batch = EmbeddingBatch::factory()->create([
            'total_items' => 3,
            'processed_items' => 2,
            'status' => 'processing',
        ]);

        $batch->incrementProcessed(500);

        $batch->refresh();

        $this->assertEquals('completed', $batch->status);
        $this->assertNotNull($batch->completed_at);
    }

    /** @test */
    public function it_marks_as_processing()
    {
        $batch = EmbeddingBatch::factory()->create([
            'status' => 'pending',
            'started_at' => null,
        ]);

        $batch->markProcessing();

        $batch->refresh();

        $this->assertEquals('processing', $batch->status);
        $this->assertNotNull($batch->started_at);
    }

    /** @test */
    public function scope_pending_returns_only_pending_batches()
    {
        EmbeddingBatch::factory()->create(['status' => 'pending']);
        EmbeddingBatch::factory()->create(['status' => 'pending']);
        EmbeddingBatch::factory()->processing()->create();

        $pending = EmbeddingBatch::pending()->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn ($b) => $b->status === 'pending'));
    }

    /** @test */
    public function scope_processing_returns_only_processing_batches()
    {
        EmbeddingBatch::factory()->processing()->create();
        EmbeddingBatch::factory()->processing()->create();
        EmbeddingBatch::factory()->completed()->create();

        $processing = EmbeddingBatch::processing()->get();

        $this->assertCount(2, $processing);
        $this->assertTrue($processing->every(fn ($b) => $b->status === 'processing'));
    }

    /** @test */
    public function scope_completed_returns_only_completed_batches()
    {
        EmbeddingBatch::factory()->completed()->create();
        EmbeddingBatch::factory()->processing()->create();
        EmbeddingBatch::factory()->completed()->create();

        $completed = EmbeddingBatch::completed()->get();

        $this->assertCount(2, $completed);
        $this->assertTrue($completed->every(fn ($b) => $b->status === 'completed'));
    }

    /** @test */
    public function it_handles_different_source_types()
    {
        $lawsBatch = EmbeddingBatch::factory()->create(['source_type' => 'laws']);
        $casesBatch = EmbeddingBatch::factory()->create(['source_type' => 'cases_documents']);
        $decisionsBatch = EmbeddingBatch::factory()->create(['source_type' => 'court_decisions_documents']);

        $this->assertEquals('laws', $lawsBatch->source_type);
        $this->assertEquals('cases_documents', $casesBatch->source_type);
        $this->assertEquals('court_decisions_documents', $decisionsBatch->source_type);
    }

    /** @test */
    public function it_stores_embedding_model_name()
    {
        $batch = EmbeddingBatch::factory()->create([
            'embedding_model' => 'text-embedding-3-large',
        ]);

        $this->assertEquals('text-embedding-3-large', $batch->embedding_model);
    }

    /** @test */
    public function it_tracks_total_tokens_used()
    {
        $batch = EmbeddingBatch::factory()->create(['tokens_used' => 0]);

        $batch->incrementProcessed(1000);
        $batch->incrementProcessed(2000);
        $batch->incrementProcessed(1500);

        $batch->refresh();

        $this->assertEquals(4500, $batch->tokens_used);
    }

    /** @test */
    public function it_accumulates_cost_across_multiple_increments()
    {
        $batch = EmbeddingBatch::factory()->create([
            'cost' => '0.0000',
            'total_items' => 10,
        ]);

        $batch->incrementProcessed(10000); // $0.0002
        $batch->incrementProcessed(5000);  // $0.0001

        $batch->refresh();

        $this->assertEqualsWithDelta(0.0003, (float) $batch->cost, 0.000001);
    }
}
