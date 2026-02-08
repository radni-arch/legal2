<?php

namespace Tests\Unit\Models;

use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TextractDocumentTest extends TestCase
{
    use UsesTestDatabase;

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12345',
            'drive_file_id' => 'test-file-123',
            'drive_file_name' => 'test.pdf',
            'status' => 'succeeded',
        ]);

        // Act
        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Sample document content',
            'chunk_index' => 0,
            'chunk_overlap' => 100,
            'embedding' => array_fill(0, 1536, 0.1),
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'token_count' => 150,
            'processing_status' => 'completed',
            'metadata' => ['source' => 'textract'],
            'case_id' => $case->id,
        ]);

        // Assert
        $this->assertEquals($job->id, $document->textract_job_id);
        $this->assertEquals('Sample document content', $document->content);
        $this->assertEquals(0, $document->chunk_index);
        $this->assertEquals('completed', $document->processing_status);
    }

    public function test_belongs_to_textract_job(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12346',
            'drive_file_id' => 'test-file-456',
            'drive_file_name' => 'test2.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Content',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Act
        $relatedJob = $document->textractJob;

        // Assert
        $this->assertInstanceOf(TextractJob::class, $relatedJob);
        $this->assertEquals($job->id, $relatedJob->id);
        $this->assertEquals('test2.pdf', $relatedJob->drive_file_name);
    }

    public function test_belongs_to_case(): void
    {
        // Arrange
        $case = LegalCase::create([
            'id' => '01HXC9K8P5B6M2QWERTY129990',
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12347',
            'drive_file_id' => 'test-file-789',
            'drive_file_name' => 'test3.pdf',
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $case->id,
            'content' => 'Case document content',
            'processing_status' => 'pending',
        ]);

        // Act
        $relatedCase = $document->case;

        // Assert
        $this->assertInstanceOf(LegalCase::class, $relatedCase);
        $this->assertEquals($case->id, $relatedCase->id);
        $this->assertEquals('Test Case', $relatedCase->title);
    }

    public function test_scope_processed_returns_only_completed_documents(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12348',
            'drive_file_id' => 'test-file-scope',
            'drive_file_name' => 'scope.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Pending doc',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Completed doc 1',
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Completed doc 2',
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $processed = TextractDocument::processed()->get();

        // Assert
        $this->assertCount(2, $processed);
        $this->assertTrue($processed->every(fn ($d) => $d->processing_status === 'completed'));
    }

    public function test_scope_pending_returns_only_pending_documents(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12349',
            'drive_file_id' => 'test-file-pending',
            'drive_file_name' => 'pending.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Pending doc 1',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Pending doc 2',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Completed doc',
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $pending = TextractDocument::pending()->get();

        // Assert
        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn ($d) => $d->processing_status === 'pending'));
    }

    public function test_scope_failed_returns_only_failed_documents(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12350',
            'drive_file_id' => 'test-file-failed',
            'drive_file_name' => 'failed.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Failed doc',
            'processing_status' => 'failed',
            'processing_error' => 'Error occurred',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Completed doc',
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $failed = TextractDocument::failed()->get();

        // Assert
        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->processing_status);
    }

    public function test_scope_for_case_filters_by_case_id(): void
    {
        // Arrange
        $case1 = LegalCase::create(['id' => '01HXC9K8P5B6M2QWERTY224560', 'title' => 'Case 1', 'status' => 'active']);
        $case2 = LegalCase::create(['id' => '01HXC9K8P5B6M2QWERTY227890', 'title' => 'Case 2', 'status' => 'active']);

        $job = TextractJob::create([
            'id' => '01HXC9K8P5B6M2QWERTY12351',
            'drive_file_id' => 'test-file-case',
            'drive_file_name' => 'case.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $case1->id,
            'content' => 'Case 1 doc',
            'processing_status' => 'completed',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'case_id' => $case2->id,
            'content' => 'Case 2 doc',
            'processing_status' => 'completed',
        ]);

        // Act
        $case1Docs = TextractDocument::forCase($case1->id)->get();

        // Assert
        $this->assertCount(1, $case1Docs);
        $this->assertEquals($case1->id, $case1Docs->first()->case_id);
    }

    public function test_scope_ordered_sorts_by_chunk_index(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12352',
            'drive_file_id' => 'test-file-ordered',
            'drive_file_name' => 'ordered.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Chunk 2',
            'chunk_index' => 2,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Chunk 0',
            'chunk_index' => 0,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Chunk 1',
            'chunk_index' => 1,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $ordered = TextractDocument::ordered()->get();

        // Assert
        $this->assertCount(3, $ordered);
        $this->assertEquals(0, $ordered[0]->chunk_index);
        $this->assertEquals(1, $ordered[1]->chunk_index);
        $this->assertEquals(2, $ordered[2]->chunk_index);
    }

    public function test_has_embedding_returns_true_when_embedded(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12353',
            'drive_file_id' => 'test-file-embed',
            'drive_file_name' => 'embed.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Embedded content',
            'embedding' => array_fill(0, 1536, 0.1),
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $hasEmbedding = $document->hasEmbedding();

        // Assert
        $this->assertTrue($hasEmbedding);
    }

    public function test_has_embedding_returns_false_when_no_embedding(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12354',
            'drive_file_id' => 'test-file-no-embed',
            'drive_file_name' => 'no-embed.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'No embedding content',
            'embedding' => null,
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Act
        $hasEmbedding = $document->hasEmbedding();

        // Assert
        $this->assertFalse($hasEmbedding);
    }

    public function test_has_embedding_returns_false_when_not_completed(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12355',
            'drive_file_id' => 'test-file-not-complete',
            'drive_file_name' => 'not-complete.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Not completed content',
            'embedding' => array_fill(0, 1536, 0.1),
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Act
        $hasEmbedding = $document->hasEmbedding();

        // Assert
        $this->assertFalse($hasEmbedding);
    }

    public function test_mark_as_embedded_updates_document(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12356',
            'drive_file_id' => 'test-file-mark',
            'drive_file_name' => 'mark.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'To be embedded',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        $embedding = array_fill(0, 1536, 0.5);
        $metadata = [
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ];

        // Act
        $document->markAsEmbedded($embedding, $metadata);

        // Assert
        $document->refresh();
        $this->assertEquals('completed', $document->processing_status);
        $this->assertNotNull($document->embedding);
        $this->assertCount(1536, $document->embedding);
        $this->assertEquals('openai', $document->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $document->embedding_model);
        $this->assertEquals(1536, $document->embedding_dimensions);
        $this->assertNotNull($document->embedded_at);
        $this->assertNull($document->processing_error);
    }

    public function test_mark_as_failed_updates_document(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12357',
            'drive_file_id' => 'test-file-fail',
            'drive_file_name' => 'fail.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Will fail',
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Act
        $document->markAsFailed('API rate limit exceeded');

        // Assert
        $document->refresh();
        $this->assertEquals('failed', $document->processing_status);
        $this->assertEquals('API rate limit exceeded', $document->processing_error);
    }

    public function test_cosine_similarity_calculates_correctly(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12358',
            'drive_file_id' => 'test-file-cosine',
            'drive_file_name' => 'cosine.pdf',
            'status' => 'succeeded',
        ]);

        // Create identical vectors (should have similarity of 1.0)
        $vector = array_fill(0, 10, 0.5);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Cosine test',
            'embedding' => $vector,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $similarity = $document->cosineSimilarity($vector);

        // Assert
        $this->assertNotNull($similarity);
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_returns_null_when_no_embedding(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12359',
            'drive_file_id' => 'test-file-no-cosine',
            'drive_file_name' => 'no-cosine.pdf',
            'status' => 'succeeded',
        ]);

        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'No embedding',
            'embedding' => null,
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Act
        $similarity = $document->cosineSimilarity([0.1, 0.2, 0.3]);

        // Assert
        $this->assertNull($similarity);
    }

    public function test_get_full_content_combines_chunks(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12360',
            'drive_file_id' => 'test-file-full',
            'drive_file_name' => 'full.pdf',
            'status' => 'succeeded',
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'First chunk',
            'chunk_index' => 0,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Second chunk',
            'chunk_index' => 1,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Third chunk',
            'chunk_index' => 2,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Act
        $fullContent = TextractDocument::getFullContent($job->id);

        // Assert
        $this->assertStringContainsString('First chunk', $fullContent);
        $this->assertStringContainsString('Second chunk', $fullContent);
        $this->assertStringContainsString('Third chunk', $fullContent);
    }

    public function test_casts_arrays_correctly(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12361',
            'drive_file_id' => 'test-file-cast',
            'drive_file_name' => 'cast.pdf',
            'status' => 'succeeded',
        ]);

        // Act
        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Cast test',
            'embedding' => [0.1, 0.2, 0.3],
            'metadata' => ['key' => 'value', 'count' => 5],
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Assert
        $this->assertIsArray($document->embedding);
        $this->assertIsArray($document->metadata);
        $this->assertEquals('value', $document->metadata['key']);
    }

    public function test_casts_datetime_correctly(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12362',
            'drive_file_id' => 'test-file-datetime',
            'drive_file_name' => 'datetime.pdf',
            'status' => 'succeeded',
        ]);

        $now = now();

        // Act
        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Datetime test',
            'embedded_at' => $now,
            'processing_status' => 'completed',
            'case_id' => $case->id,
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $document->embedded_at);
    }

    public function test_stores_chunk_overlap(): void
    {
        // Arrange

        $case = LegalCase::factory()->create();
        $job = TextractJob::create([
            'case_id' => $case->id,
            'id' => '01HXC9K8P5B6M2QWERTY12363',
            'drive_file_id' => 'test-file-overlap',
            'drive_file_name' => 'overlap.pdf',
            'status' => 'succeeded',
        ]);

        // Act
        $document = TextractDocument::create([
            'textract_job_id' => $job->id,
            'content' => 'Overlap test',
            'chunk_index' => 1,
            'chunk_overlap' => 200,
            'processing_status' => 'pending',
            'case_id' => $case->id,
        ]);

        // Assert
        $this->assertEquals(200, $document->chunk_overlap);
    }
}
