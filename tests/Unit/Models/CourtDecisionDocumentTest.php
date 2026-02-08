<?php

namespace Tests\Unit\Models;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Models\CourtDecisionDocumentUpload;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CourtDecisionDocumentTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_uses_string_primary_key()
    {
        $document = new CourtDecisionDocument(['id' => 'test-doc-id']);

        $this->assertFalse($document->incrementing);
        $this->assertEquals('string', $document->getKeyType());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        // Create parent decision first
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Test-Fill-123/2024',
            'title' => 'Test Fillable Decision',
        ]);

        // Create upload for relationship test
        $upload = CourtDecisionDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-upload-fill',
            'local_path' => '/uploads/fill-test.pdf',
        ]);

        $data = [
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-789',
            'upload_id' => $upload->id,
            'title' => 'Court Decision Document',
            'category' => 'judgment',
            'author' => 'Judge Smith',
            'language' => 'hr',
            'tags' => ['criminal', 'appeal'],
            'chunk_index' => 0,
            'content' => 'Decision content...',
            'metadata' => ['court' => 'Supreme Court'],
            'source' => 'court_system',
            'source_id' => 'court-doc-123',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Decision content...'),
            'embedding' => array_fill(0, 1536, 0.5),
        ];

        $document = CourtDecisionDocument::create($data);

        $this->assertEquals($data['title'], $document->title);
        $this->assertEquals($data['category'], $document->category);
        $this->assertEquals($data['language'], $document->language);
    }

    /** @test */
    public function it_belongs_to_court_decision()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'K-456/2024',
            'title' => 'Criminal Case',
        ]);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-test-123',
            'title' => 'Decision Document',
            'content' => 'Test content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Test content'),
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $this->assertInstanceOf(CourtDecision::class, $document->decision);
        $this->assertEquals($decision->id, $document->decision->id);
    }

    /** @test */
    public function it_belongs_to_upload()
    {
        // Create parent decision for upload
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Upload-Test-789/2024',
            'title' => 'Upload Test Decision',
        ]);

        $upload = CourtDecisionDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-upload-test',
            'local_path' => '/uploads/upload-test.pdf',
        ]);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-test-456',
            'upload_id' => $upload->id,
            'content' => 'Test content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Test content'),
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $this->assertInstanceOf(CourtDecisionDocumentUpload::class, $document->upload);
        $this->assertEquals($upload->id, $document->upload->id);
    }

    /** @test */
    public function it_casts_tags_as_array()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Tags-Test-101/2024',
            'title' => 'Tags Test Decision',
        ]);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-tags-test',
            'tags' => ['appeal', 'criminal', 'precedent'],
            'content' => 'Test content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Test content'),
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $this->assertIsArray($document->tags);
        $this->assertCount(3, $document->tags);
        $this->assertContains('appeal', $document->tags);
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Meta-Test-202/2024',
            'title' => 'Metadata Test Decision',
        ]);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-metadata-test',
            'metadata' => ['court' => 'Supreme Court', 'judges' => 3],
            'content' => 'Test content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Test content'),
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $this->assertIsArray($document->metadata);
        $this->assertEquals('Supreme Court', $document->metadata['court']);
    }

    /** @test */
    public function it_casts_embedding_vector_as_array()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'Embed-Test-404/2024',
            'title' => 'Embedding Test Decision',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-test',
            'content' => 'Test content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'test'),
            'chunk_index' => 0,
            'embedding' => $embedding,
        ]);

        $this->assertIsArray($document->embedding);
        $this->assertCount(1536, $document->embedding);
    }

    /** @test */
    public function it_stores_croatian_language_content()
    {
        $decision = CourtDecision::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'HR-Test-303/2024',
            'title' => 'Croatian Language Test Decision',
        ]);

        $document = CourtDecisionDocument::create([
            'id' => Str::ulid()->toString(),
            'decision_id' => $decision->id,
            'doc_id' => 'doc-croatian-test',
            'title' => 'Presuda Vrhovnog suda',
            'content' => 'Sud je odlučio...',
            'language' => 'hr',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'content_hash' => hash('sha256', 'Sud je odlučio...'),
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1536, 0.5),
        ]);

        $this->assertEquals('hr', $document->language);
        $this->assertStringContainsString('Presuda', $document->title);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.court_decision_documents' => 'custom_decision_docs']);

        $document = new CourtDecisionDocument;

        $this->assertEquals('custom_decision_docs', $document->getTable());
    }
}
