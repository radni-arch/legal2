<?php

namespace Tests\Unit\Models;

use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractJobTest extends TestCase
{
    use UsesTestDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12345',
            'drive_file_id' => 'drive-file-123',
            'drive_file_name' => 'document.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Extracted text content',
            'metadata' => ['page_count' => 10],
        ]);

        // Assert
        $this->assertEquals('drive-file-123', $job->drive_file_id);
        $this->assertEquals('succeeded', $job->status);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $case = LegalCase::factory()->create();

        $job = TextractJob::create([
            'drive_file_id' => 'gdrive-123',
            'drive_file_name' => 'document.pdf',
            'case_id' => $case->id,
            's3_key' => 'textract/input/document.pdf',
            'job_id' => 'textract-job-456',
            'status' => 'queued',
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);

        // Assert
        $this->assertEquals('gdrive-123', $job->drive_file_id);
        $this->assertEquals('document.pdf', $job->drive_file_name);
        $this->assertEquals('queued', $job->status);
        $this->assertEquals($case->id, $job->case_id);
    }

    public function test_belongs_to_case(): void
    {
        // Arrange
        $case = LegalCase::factory()->create([
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12346',
            'drive_file_id' => 'file-456',
            'drive_file_name' => 'case-doc.pdf',
            'case_id' => $case->id,
            'status' => 'pending',
        ]);

        // Act
        $relatedCase = $job->case;

        // Assert
        $this->assertInstanceOf(LegalCase::class, $relatedCase);
        $this->assertEquals($case->id, $relatedCase->id);
        $this->assertEquals('Test Case', $relatedCase->title);
    }

    public function test_belongs_to_editor(): void
    {
        // Arrange
        $user = User::factory()->create(['name' => 'John Editor']);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12347',
            'drive_file_id' => 'file-789',
            'drive_file_name' => 'edited-doc.pdf',
            'status' => 'succeeded',
            'edited_by' => $user->id,
        ]);

        // Act
        $editor = $job->editor;

        // Assert
        $this->assertInstanceOf(User::class, $editor);
        $this->assertEquals($user->id, $editor->id);
        $this->assertEquals('John Editor', $editor->name);
    }

    public function test_has_many_documents(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12348',
            'drive_file_id' => 'file-docs',
            'drive_file_name' => 'multi-chunk.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Chunk 1',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Chunk 2',
            'chunk_index' => 1,
            'processing_status' => 'completed',
        ]);

        // Act
        $documents = $job->documents;

        // Assert
        $this->assertCount(2, $documents);
        $this->assertInstanceOf(TextractDocument::class, $documents->first());
    }

    public function test_get_effective_content_returns_manual_when_edited(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12349',
            'drive_file_id' => 'file-manual',
            'drive_file_name' => 'manual.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Original extracted content',
            'manual_content' => 'Manually edited content',
            'manually_edited' => true,
        ]);

        // Act
        $effectiveContent = $job->effective_content;

        // Assert
        $this->assertEquals('Manually edited content', $effectiveContent);
    }

    public function test_get_effective_content_returns_extracted_when_not_edited(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12350',
            'drive_file_id' => 'file-extracted',
            'drive_file_name' => 'extracted.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Original extracted content',
            'manually_edited' => false,
        ]);

        // Act
        $effectiveContent = $job->effective_content;

        // Assert
        $this->assertEquals('Original extracted content', $effectiveContent);
    }

    public function test_is_ready_for_embedding_returns_true_when_ready(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12351',
            'drive_file_id' => 'file-ready-embed',
            'drive_file_name' => 'ready-embed.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content ready for embedding',
            'embedding_status' => 'pending',
        ]);

        // Act
        $isReady = $job->isReadyForEmbedding();

        // Assert
        $this->assertTrue($isReady);
    }

    public function test_is_ready_for_embedding_returns_false_when_no_content(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12352',
            'drive_file_id' => 'file-no-content',
            'drive_file_name' => 'no-content.pdf',
            'status' => 'succeeded',
            'extracted_content' => '',
            'embedding_status' => 'pending',
        ]);

        // Act
        $isReady = $job->isReadyForEmbedding();

        // Assert
        $this->assertFalse($isReady);
    }

    public function test_is_ready_for_embedding_returns_false_when_not_succeeded(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12353',
            'drive_file_id' => 'file-pending',
            'drive_file_name' => 'pending.pdf',
            'status' => 'pending',
            'extracted_content' => 'Some content',
            'embedding_status' => 'pending',
        ]);

        // Act
        $isReady = $job->isReadyForEmbedding();

        // Assert
        $this->assertFalse($isReady);
    }

    public function test_is_ready_for_graph_sync_returns_true_when_ready(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12354',
            'drive_file_id' => 'file-ready-graph',
            'drive_file_name' => 'ready-graph.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Content ready for graph sync',
            'graph_sync_status' => 'pending',
        ]);

        // Act
        $isReady = $job->isReadyForGraphSync();

        // Assert
        $this->assertTrue($isReady);
    }

    public function test_mark_as_edited_updates_fields_and_resets_sync_status(): void
    {
        // Arrange
        $user = User::factory()->create();

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12355',
            'drive_file_id' => 'file-to-edit',
            'drive_file_name' => 'to-edit.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Original content',
        ]);

        // Act
        $job->markAsEdited($user->id, 'Edited content');

        // Assert
        $this->assertTrue($job->manually_edited);
        $this->assertEquals($user->id, $job->edited_by);
    }

    /** @test */
    public function it_belongs_to_legal_case()
    {
        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create(['case_id' => $case->id]);

        $this->assertInstanceOf(LegalCase::class, $job->case);
        $this->assertEquals($case->id, $job->case->id);
    }

    /** @test */
    public function it_belongs_to_editor_user()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->create([
            'edited_by' => $user->id,
            'manually_edited' => true,
        ]);

        $this->assertInstanceOf(User::class, $job->editor);
        $this->assertEquals($user->id, $job->editor->id);
    }

    /** @test */
    public function it_has_many_documents()
    {
        $job = TextractJob::factory()->create();
        TextractDocument::factory()->count(3)->create(['textract_job_id' => $job->id]);

        $this->assertCount(3, $job->documents);
        $this->assertInstanceOf(TextractDocument::class, $job->documents->first());
    }

    /** @test */
    public function it_returns_manual_content_when_manually_edited()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Original OCR content',
            'manual_content' => 'User edited content',
            'manually_edited' => true,
        ]);

        $this->assertEquals('User edited content', $job->effective_content);
    }

    /** @test */
    public function it_returns_extracted_content_when_not_manually_edited()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Original OCR content',
            'manual_content' => null,
            'manually_edited' => false,
        ]);

        $this->assertEquals('Original OCR content', $job->effective_content);
    }

    /** @test */
    public function it_prefers_manual_content_over_extracted_when_both_exist()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'OCR content',
            'manual_content' => 'Manual content',
            'manually_edited' => true,
        ]);

        $this->assertEquals('Manual content', $job->effective_content);
    }

    /** @test */
    public function it_checks_if_ready_for_embedding()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Test content',
            'status' => 'succeeded',
            'embedding_status' => 'pending',
        ]);

        $this->assertTrue($job->isReadyForEmbedding());
    }

    /** @test */
    public function it_is_not_ready_for_embedding_without_content()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => null,
            'status' => 'succeeded',
            'embedding_status' => 'pending',
        ]);

        $this->assertFalse($job->isReadyForEmbedding());
    }

    /** @test */
    public function it_is_not_ready_for_embedding_if_status_not_succeeded()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Test content',
            'status' => 'analyzing',
            'embedding_status' => 'pending',
        ]);

        $this->assertFalse($job->isReadyForEmbedding());
    }

    /** @test */
    public function it_is_not_ready_for_embedding_if_already_synced()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Test content',
            'status' => 'succeeded',
            'embedding_status' => 'synced',
        ]);

        $this->assertFalse($job->isReadyForEmbedding());
    }

    /** @test */
    public function it_checks_if_ready_for_graph_sync()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Test content',
            'status' => 'succeeded',
            'graph_sync_status' => 'pending',
        ]);

        $this->assertTrue($job->isReadyForGraphSync());
    }

    /** @test */
    public function it_is_not_ready_for_graph_sync_if_already_synced()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'Test content',
            'status' => 'succeeded',
            'graph_sync_status' => 'synced',
        ]);

        $this->assertFalse($job->isReadyForGraphSync());
    }

    /** @test */
    public function it_marks_job_as_edited()
    {
        $user = User::factory()->create();
        $job = TextractJob::factory()->create([
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Act
        $job->markAsEdited($user->id);

        // Assert
        $job->refresh();
        $job->markAsEdited($user->id);

        $job->refresh();

        $this->assertTrue($job->manually_edited);
        $this->assertEquals($user->id, $job->edited_by);
        $this->assertNotNull($job->content_edited_at);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    public function test_mark_embedding_synced_updates_status_and_timestamp(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12356',
            'drive_file_id' => 'file-embed-sync',
            'drive_file_name' => 'embed-sync.pdf',
            'status' => 'succeeded',
            'embedding_status' => 'pending',
        ]);

        // Act
        $job->markEmbeddingSynced();

        // Assert
        $job->refresh();
        $this->assertEquals('synced', $job->embedding_status);
        $this->assertNotNull($job->embedding_synced_at);
    }

    /** @test */
    public function it_marks_embedding_as_synced()
    {
        $job = TextractJob::factory()->create([
            'embedding_status' => 'pending',
        ]);

        $job->markEmbeddingSynced();

        $job->refresh();

        $this->assertEquals('synced', $job->embedding_status);
        $this->assertNotNull($job->embedding_synced_at);
    }

    public function test_mark_graph_synced_updates_status_and_timestamp(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12357',
            'drive_file_id' => 'file-graph-sync',
            'drive_file_name' => 'graph-sync.pdf',
            'status' => 'succeeded',
            'graph_sync_status' => 'pending',
        ]);

        // Act
        $job->markGraphSynced();

        // Assert
        $job->refresh();
        $this->assertEquals('synced', $job->graph_sync_status);
        $this->assertNotNull($job->graph_synced_at);
    }

    /** @test */
    public function it_marks_graph_as_synced()
    {
        $job = TextractJob::factory()->create([
            'graph_sync_status' => 'pending',
        ]);

        $job->markGraphSynced();

        $job->refresh();

        $this->assertEquals('synced', $job->graph_sync_status);
        $this->assertNotNull($job->graph_synced_at);
    }

    public function test_casts_arrays_correctly(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12358',
            'drive_file_id' => 'file-cast',
            'drive_file_name' => 'cast.pdf',
            'status' => 'succeeded',
            'metadata' => ['key' => 'value', 'page_count' => 5],
            'performance_metrics' => ['duration' => 120, 'pages' => 10],
        ]);

        // Assert
        $this->assertIsArray($job->metadata);
        $this->assertIsArray($job->performance_metrics);
        $this->assertEquals('value', $job->metadata['key']);
        $this->assertEquals(120, $job->performance_metrics['duration']);
    }

    public function test_casts_boolean_correctly(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12359',
            'drive_file_id' => 'file-bool',
            'drive_file_name' => 'bool.pdf',
            'status' => 'succeeded',
            'manually_edited' => true,
        ]);

        // Assert
        $this->assertIsBool($job->manually_edited);
        $this->assertTrue($job->manually_edited);
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $metadata = ['pages' => 10, 'size' => '2.5MB'];
        $job = TextractJob::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($job->metadata);
        $this->assertEquals(10, $job->metadata['pages']);
    }

    /** @test */
    public function it_casts_manually_edited_as_boolean()
    {
        $job = TextractJob::factory()->create(['manually_edited' => true]);

        $this->assertIsBool($job->manually_edited);
        $this->assertTrue($job->manually_edited);
    }

    public function test_casts_datetime_correctly(): void
    {
        // Arrange
        $now = now();

        // Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12360',
            'drive_file_id' => 'file-datetime',
            'drive_file_name' => 'datetime.pdf',
            'status' => 'succeeded',
            'content_edited_at' => $now,
            'embedding_synced_at' => $now,
            'graph_synced_at' => $now,
            'queued_at' => $now,
            'processing_started_at' => $now,
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->content_edited_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->embedding_synced_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->graph_synced_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->queued_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->processing_started_at);
    }

    /** @test */
    public function it_casts_timestamp_fields_as_datetime()
    {
        $job = TextractJob::factory()->create([
            'content_edited_at' => now(),
            'embedding_synced_at' => now(),
            'graph_synced_at' => now(),
            'queued_at' => now(),
            'processing_started_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->content_edited_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->embedding_synced_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->graph_synced_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->queued_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $job->processing_started_at);
    }

    public function test_stores_distributed_processing_fields(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12361',
            'drive_file_id' => 'file-distributed',
            'drive_file_name' => 'distributed.pdf',
            'status' => 'processing',
            'batch_id' => null,  // Nullable field, no batch association
            'queue_name' => 'textract-high-priority',
            'priority' => 1,
            'retry_count' => 0,
            'worker_id' => getmypid(),
            'queued_at' => now(),
        ]);

        // Assert
        $this->assertNull($job->batch_id);  // No batch association
        $this->assertEquals('textract-high-priority', $job->queue_name);
        $this->assertEquals(1, $job->priority);
        $this->assertEquals(0, $job->retry_count);
        $this->assertNotNull($job->worker_id);
    }

    public function test_stores_s3_key_and_job_id(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12362',
            'drive_file_id' => 'file-s3',
            'drive_file_name' => 's3.pdf',
            'status' => 'processing',
            's3_key' => 'textract/input/file-s3.pdf',
            'job_id' => 'aws-textract-job-123',
        ]);

        // Assert
        $this->assertEquals('textract/input/file-s3.pdf', $job->s3_key);
        $this->assertEquals('aws-textract-job-123', $job->job_id);
    }

    public function test_stores_error_information(): void
    {
        // Arrange & Act
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12363',
            'drive_file_id' => 'file-error',
            'drive_file_name' => 'error.pdf',
            'status' => 'failed',
            'error' => 'Textract API timeout after 30 seconds',
        ]);

        // Assert
        $this->assertEquals('failed', $job->status);
        $this->assertEquals('Textract API timeout after 30 seconds', $job->error);
    }

    public function test_deleting_job_cascades_to_documents(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12364',
            'drive_file_id' => 'file-cascade',
            'drive_file_name' => 'cascade.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Document 1',
            'chunk_index' => 0,
            'processing_status' => 'completed',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Document 2',
            'chunk_index' => 1,
            'processing_status' => 'completed',
        ]);

        // Act
        $job->delete();

        // Assert
        $this->assertDatabaseMissing('textract_jobs', ['id' => $job->id]);
        $this->assertDatabaseMissing('textract_documents', ['textract_job_id' => $job->id]);
    }

    public function test_effective_content_prefers_manual_over_extracted(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12365',
            'drive_file_id' => 'file-prefer-manual',
            'drive_file_name' => 'prefer-manual.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Extracted content with errors',
            'manual_content' => 'Corrected manual content',
            'manually_edited' => true,
        ]);

        // Act
        $content = $job->effective_content;

        // Assert
        $this->assertEquals('Corrected manual content', $content);
        $this->assertNotEquals('Extracted content with errors', $content);
    }

    public function test_effective_content_returns_null_when_no_content(): void
    {
        // Arrange
        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12366',
            'drive_file_id' => 'file-no-content',
            'drive_file_name' => 'no-content.pdf',
            'status' => 'pending',
        ]);

        // Act
        $content = $job->effective_content;

        // Assert
        $this->assertNull($content);
    }

    public function test_stores_performance_metrics(): void
    {
        // Arrange & Act
        $metrics = [
            'duration_seconds' => 45.5,
            'pages_processed' => 10,
            'blocks_extracted' => 500,
            'tables_extracted' => 3,
            'worker_id' => 12345,
            'memory_peak_mb' => 256.5,
        ];

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12367',
            'drive_file_id' => 'file-metrics',
            'drive_file_name' => 'metrics.pdf',
            'status' => 'succeeded',
            'performance_metrics' => $metrics,
        ]);

        // Assert
        $this->assertEquals(45.5, $job->performance_metrics['duration_seconds']);
        $this->assertEquals(10, $job->performance_metrics['pages_processed']);
        $this->assertEquals(500, $job->performance_metrics['blocks_extracted']);
    }

    /** @test */
    public function it_handles_distributed_processing_fields()
    {
        $job = TextractJob::factory()->create([
            'batch_id' => null,  // Nullable field, no batch association needed for this test
            'queue_name' => 'textract-high-priority',
            'priority' => 10,
            'retry_count' => 2,
            'worker_id' => 'worker-456',
        ]);

        $this->assertNull($job->batch_id);
        $this->assertEquals('textract-high-priority', $job->queue_name);
        $this->assertEquals(10, $job->priority);
        $this->assertEquals(2, $job->retry_count);
        $this->assertEquals('worker-456', $job->worker_id);
    }

    /** @test */
    public function it_stores_performance_metrics_as_array()
    {
        $metrics = [
            'ocr_duration' => 12.5,
            'upload_duration' => 3.2,
            'total_duration' => 15.7,
        ];

        $job = TextractJob::factory()->create(['performance_metrics' => $metrics]);

        $this->assertIsArray($job->performance_metrics);
        $this->assertEquals(12.5, $job->performance_metrics['ocr_duration']);
    }

    /** @test */
    public function it_handles_status_transitions()
    {
        $job = TextractJob::factory()->create(['status' => 'queued']);
        $this->assertEquals('queued', $job->status);

        $job->update(['status' => 'analyzing']);
        $this->assertEquals('analyzing', $job->status);

        $job->update(['status' => 'succeeded']);
        $this->assertEquals('succeeded', $job->status);
    }

    /** @test */
    public function it_handles_failed_status()
    {
        $job = TextractJob::factory()->create([
            'status' => 'failed',
            'error' => 'Textract API error: timeout',
        ]);

        $this->assertEquals('failed', $job->status);
        $this->assertNotNull($job->error);
        $this->assertStringContainsString('timeout', $job->error);
    }

    /** @test */
    public function it_deletes_related_documents_when_deleted()
    {
        $job = TextractJob::factory()->create();
        TextractDocument::factory()->count(3)->create(['textract_job_id' => $job->id]);

        $this->assertCount(3, TextractDocument::where('textract_job_id', $job->id)->get());

        $job->delete();

        $this->assertCount(0, TextractDocument::where('textract_job_id', $job->id)->get());
    }

    /** @test */
    public function it_handles_s3_key_storage()
    {
        $job = TextractJob::factory()->create([
            's3_key' => 'textract/input/2024/01/document-123.pdf',
        ]);

        $this->assertEquals('textract/input/2024/01/document-123.pdf', $job->s3_key);
    }

    /** @test */
    public function it_stores_textract_job_id_from_aws()
    {
        $job = TextractJob::factory()->create([
            'job_id' => 'aws-textract-job-abc123def456',
        ]);

        $this->assertEquals('aws-textract-job-abc123def456', $job->job_id);
    }

    /** @test */
    public function it_uses_manual_content_when_set()
    {
        $job = TextractJob::factory()->create([
            'extracted_content' => 'OCR result',
            'manual_content' => 'Corrected by user',
            'manually_edited' => true,
        ]);

        $this->assertEquals('Corrected by user', $job->effective_content);
        $this->assertNotEquals($job->extracted_content, $job->effective_content);
    }

    /**
     * @test
     * Test that content update only dispatches RegenerateTextractEmbeddings, NOT SyncTextractToGraph.
     * This prevents the race condition where graph sync is attempted before embeddings are ready.
     */
    public function it_dispatches_only_embedding_regeneration_on_content_update_not_graph_sync()
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Config::set('textract.auto_sync', true);
        \Illuminate\Support\Facades\Config::set('neo4j.sync.enabled', true);

        // Create a succeeded job with initial content
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original content',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Update the manual content (triggers model event)
        $job->update([
            'manual_content' => 'Updated manual content',
            'manually_edited' => true,
        ]);

        // Should dispatch RegenerateTextractEmbeddings
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id;
        });

        // Should NOT dispatch SyncTextractToGraph (this was the race condition bug)
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test that updating extracted_content also only dispatches embeddings, not graph sync.
     */
    public function it_dispatches_only_embedding_regeneration_on_extracted_content_update()
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Config::set('textract.auto_sync', true);
        \Illuminate\Support\Facades\Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original extracted content',
            'manually_edited' => false,
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        // Update extracted content
        $job->update([
            'extracted_content' => 'Updated extracted content',
        ]);

        // Should dispatch RegenerateTextractEmbeddings
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class);

        // Should NOT dispatch SyncTextractToGraph directly
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test that no jobs are dispatched when auto_sync is disabled.
     */
    public function it_does_not_dispatch_jobs_when_auto_sync_disabled()
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Config::set('textract.auto_sync', false);
        \Illuminate\Support\Facades\Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Original content',
            'embedding_status' => 'synced',
        ]);

        $job->update([
            'manual_content' => 'Updated content',
            'manually_edited' => true,
        ]);

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\RegenerateTextractEmbeddings::class);
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test that no jobs are dispatched when job status is not succeeded/completed.
     */
    public function it_does_not_dispatch_jobs_when_job_not_succeeded()
    {
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Config::set('textract.auto_sync', true);
        \Illuminate\Support\Facades\Config::set('neo4j.sync.enabled', true);

        $job = TextractJob::factory()->create([
            'status' => 'processing', // Not succeeded
            'extracted_content' => 'Original content',
        ]);

        $job->update([
            'extracted_content' => 'Updated content',
        ]);

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\RegenerateTextractEmbeddings::class);
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncTextractToGraph::class);
    }

    /**
     * @test
     * Test that graph_sync_status is blocked when embedding fails.
     */
    public function it_blocks_graph_sync_when_embedding_fails()
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'embedding_status' => 'pending',
            'graph_sync_status' => 'pending',
        ]);

        // Simulate embedding failure
        $job->update(['embedding_status' => 'failed']);

        $job->refresh();

        $this->assertEquals('failed', $job->embedding_status);
        $this->assertEquals('blocked', $job->graph_sync_status);
    }

    /**
     * @test
     * Test that needsReview() returns true when metadata flag is set.
     */
    public function test_needs_review_returns_true_when_metadata_flag_set(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence: 70.00% (threshold: 82.00%)'],
                'ocrQuality' => ['confidence' => 0.70, 'coverage' => 0.80],
            ],
        ]);

        $this->assertTrue($job->needsReview());
    }

    /**
     * @test
     * Test that needsReview() returns false when metadata flag is not set.
     */
    public function test_needs_review_returns_false_when_metadata_flag_not_set(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => [
                'ocrQuality' => ['confidence' => 0.95, 'coverage' => 0.90],
            ],
        ]);

        $this->assertFalse($job->needsReview());
    }

    /**
     * @test
     * Test that needsReview() returns false when metadata is null.
     */
    public function test_needs_review_returns_false_when_metadata_is_null(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => null,
        ]);

        $this->assertFalse($job->needsReview());
    }

    /**
     * @test
     * Test that needsReview() returns false when flag is explicitly false.
     */
    public function test_needs_review_returns_false_when_flag_explicitly_false(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => [
                'needsReview' => false,
                'ocrQuality' => ['confidence' => 0.95],
            ],
        ]);

        $this->assertFalse($job->needsReview());
    }

    /**
     * @test
     * Test that clearNeedsReview() removes the flag from metadata.
     */
    public function test_clear_needs_review_removes_flag_from_metadata(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low confidence'],
                'ocrQuality' => ['confidence' => 0.70],
            ],
        ]);

        $this->assertTrue($job->needsReview());

        $job->clearNeedsReview();
        $job->refresh();

        $this->assertFalse($job->needsReview());
        $this->assertArrayNotHasKey('needsReview', $job->metadata);
        $this->assertArrayNotHasKey('reviewReasons', $job->metadata);
        // ocrQuality should be preserved
        $this->assertArrayHasKey('ocrQuality', $job->metadata);
    }

    /**
     * @test
     * Test that clearNeedsReview() handles null metadata gracefully.
     */
    public function test_clear_needs_review_handles_null_metadata(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => null,
        ]);

        // Should not throw exception
        $job->clearNeedsReview();
        $job->refresh();

        $this->assertFalse($job->needsReview());
    }

    /**
     * @test
     * Test that getReviewReasons() returns the reasons array.
     */
    public function test_get_review_reasons_returns_reasons_array(): void
    {
        $reasons = [
            'Low overall confidence: 70.00% (threshold: 82.00%)',
            'Too many low-confidence pages: 5 (threshold: 3)',
        ];

        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => $reasons,
            ],
        ]);

        $this->assertEquals($reasons, $job->getReviewReasons());
    }

    /**
     * @test
     * Test that getReviewReasons() returns empty array when no reasons.
     */
    public function test_get_review_reasons_returns_empty_array_when_no_reasons(): void
    {
        $job = TextractJob::factory()->create([
            'status' => 'succeeded',
            'extracted_content' => 'Test content',
            'metadata' => null,
        ]);

        $this->assertEquals([], $job->getReviewReasons());
    }

}
