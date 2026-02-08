<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseDocumentTest extends TestCase
{
    use UsesTestDatabase;

    private function createTestCase(): LegalCase
    {
        return LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'TEST-'.rand(1000, 9999),
            'title' => 'Test Case',
            'status' => 'active',
        ]);
    }

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Test Document',
            'content' => 'Document content',
        ]);

        // Assert
        $this->assertIsString($document->id);
        $this->assertNotEmpty($document->id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'doc-001',
            'title' => 'Contract Agreement',
            'category' => 'contract',
            'author' => 'John Doe',
            'language' => 'hr',
            'tags' => ['contract', 'legal', 'agreement'],
            'chunk_index' => 0,
            'content' => 'This is the content of the contract agreement.',
            'metadata' => ['pages' => 10, 'format' => 'PDF'],
            'actual' => ['signed' => true, 'date' => '2024-01-15'],
            'source' => 'email',
            'source_id' => 'email-123',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 0.9876,
            'content_hash' => hash('sha256', 'content'),
            'token_count' => 250,
        ]);

        // Assert
        $this->assertEquals('doc-001', $document->doc_id);
        $this->assertEquals('Contract Agreement', $document->title);
        $this->assertEquals('contract', $document->category);
        $this->assertEquals('John Doe', $document->author);
        $this->assertEquals('hr', $document->language);
    }

    public function test_belongs_to_legal_case(): void
    {
        // Arrange
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'CASE-2024-001',
            'title' => 'Smith vs Jones',
            'status' => 'active',
        ]);

        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $case->id,
            'title' => 'Case Document',
            'content' => 'Document content',
        ]);

        // Act
        $relatedCase = $document->case;

        // Assert
        $this->assertInstanceOf(LegalCase::class, $relatedCase);
        $this->assertEquals($case->id, $relatedCase->id);
        $this->assertEquals('Smith vs Jones', $relatedCase->title);
    }

    public function test_casts_tags_as_array(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Tagged Document',
            'content' => 'Content',
            'tags' => ['evidence', 'exhibit-A', 'financial', 'confidential'],
        ]);

        // Assert
        $this->assertIsArray($document->tags);
        $this->assertCount(4, $document->tags);
        $this->assertContains('evidence', $document->tags);
        $this->assertContains('confidential', $document->tags);
    }

    public function test_casts_metadata_as_array(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $metadata = [
            'file_size' => 1048576,
            'mime_type' => 'application/pdf',
            'pages' => 25,
            'word_count' => 5000,
            'created_date' => '2024-01-15',
        ];

        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Metadata',
            'content' => 'Content',
            'metadata' => $metadata,
        ]);

        // Assert
        $this->assertIsArray($document->metadata);
        $this->assertEquals(1048576, $document->metadata['file_size']);
        $this->assertEquals('application/pdf', $document->metadata['mime_type']);
        $this->assertEquals(25, $document->metadata['pages']);
    }

    public function test_casts_actual_as_array(): void
    {
        // Arrange & Act
        $actual = [
            'signed' => true,
            'date_signed' => '2024-01-20',
            'parties' => ['Party A', 'Party B'],
            'notarized' => false,
        ];

        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Actual Data',
            'content' => 'Content',
            'actual' => $actual,
        ]);

        // Assert
        $this->assertIsArray($document->actual);
        $this->assertTrue($document->actual['signed']);
        $this->assertEquals('2024-01-20', $document->actual['date_signed']);
        $this->assertIsArray($document->actual['parties']);
        $this->assertCount(2, $document->actual['parties']);
    }

    public function test_casts_embedding_vector_as_array(): void
    {
        // Arrange & Act
        $embedding = array_fill(0, 1536, 0.123);

        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Embedding',
            'content' => 'Content',
            'embedding_vector' => $embedding,
        ]);

        // Assert
        $this->assertIsArray($document->embedding_vector);
        $this->assertCount(1536, $document->embedding_vector);
        $this->assertEquals(0.123, $document->embedding_vector[0]);
    }

    public function test_stores_document_categories(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $contract = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Contract Document',
            'content' => 'Contract content',
            'category' => 'contract',
        ]);

        $testCase = $this->createTestCase();
        $evidence = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Evidence Document',
            'content' => 'Evidence content',
            'category' => 'evidence',
        ]);

        $testCase = $this->createTestCase();
        $correspondence = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Email Correspondence',
            'content' => 'Email content',
            'category' => 'correspondence',
        ]);

        // Assert
        $this->assertEquals('contract', $contract->category);
        $this->assertEquals('evidence', $evidence->category);
        $this->assertEquals('correspondence', $correspondence->category);
    }

    public function test_stores_document_author(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Authored Document',
            'content' => 'Content',
            'author' => 'Jane Smith, Attorney',
        ]);

        // Assert
        $this->assertEquals('Jane Smith, Attorney', $document->author);
    }

    public function test_stores_chunk_index_for_large_documents(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $chunk0 = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Large Document - Part 1',
            'content' => 'First chunk content',
            'chunk_index' => 0,
        ]);

        $testCase = $this->createTestCase();
        $chunk1 = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Large Document - Part 2',
            'content' => 'Second chunk content',
            'chunk_index' => 1,
        ]);

        // Assert
        $this->assertEquals(0, $chunk0->chunk_index);
        $this->assertEquals(1, $chunk1->chunk_index);
    }

    public function test_stores_embedding_metadata(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Embedding Metadata',
            'content' => 'Content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 0.9987,
        ]);

        // Assert
        $this->assertEquals('openai', $document->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $document->embedding_model);
        $this->assertEquals(1536, $document->embedding_dimensions);
        $this->assertEquals(0.9987, $document->embedding_norm);
    }

    public function test_stores_content_hash_and_token_count(): void
    {
        // Arrange
        $content = 'Sample document content for hashing';
        $hash = hash('sha256', $content);

        // Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Hash',
            'content' => $content,
            'content_hash' => $hash,
            'token_count' => 150,
        ]);

        // Assert
        $this->assertEquals($hash, $document->content_hash);
        $this->assertEquals(64, strlen($document->content_hash)); // SHA-256 produces 64 hex chars
        $this->assertEquals(150, $document->token_count);
    }

    public function test_stores_source_information(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Source',
            'content' => 'Content',
            'source' => 'email',
            'source_id' => 'email-msg-id-12345',
        ]);

        // Assert
        $this->assertEquals('email', $document->source);
        $this->assertEquals('email-msg-id-12345', $document->source_id);
    }

    public function test_supports_multiple_languages(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $croatian = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Ugovor o radu',
            'content' => 'Sadržaj dokumenta...',
            'language' => 'hr',
        ]);

        $testCase = $this->createTestCase();
        $english = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Employment Contract',
            'content' => 'Document content...',
            'language' => 'en',
        ]);

        // Assert
        $this->assertEquals('hr', $croatian->language);
        $this->assertEquals('en', $english->language);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Minimal Document',
            'content' => 'Minimal content',
            'category' => null,
            'author' => null,
            'tags' => null,
            'metadata' => null,
            'actual' => null,
        ]);

        // Assert
        $this->assertNull($document->category);
        $this->assertNull($document->author);
        $this->assertNull($document->tags);
        $this->assertNull($document->metadata);
        $this->assertNull($document->actual);
        // Note: embedding_vector is automatically filled by the model's creating event with default values
    }

    public function test_stores_croatian_content_correctly(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Ugovor o najmu',
            'content' => 'Članak 1. Predmet ugovora\nČlanak 2. Cijena najma...',
            'author' => 'Marko Marković',
            'language' => 'hr',
        ]);

        // Assert
        $this->assertStringContainsString('Ugovor', $document->title);
        $this->assertStringContainsString('Članak', $document->content);
        $this->assertStringContainsString('Marković', $document->author);
    }

    public function test_stores_upload_id_reference(): void
    {
        // Arrange
        $testCase = $this->createTestCase();
        $upload = CaseDocumentUpload::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'upload-doc-'.rand(1000, 9999),
            'local_path' => '/tmp/test-upload.pdf',
        ]);

        // Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'upload_id' => $upload->id,
            'title' => 'Uploaded Document',
            'content' => 'Content from upload',
        ]);

        // Assert
        $this->assertNotNull($document->upload_id);
        $this->assertIsString($document->upload_id);
        $this->assertEquals($upload->id, $document->upload_id);
    }

    public function test_stores_doc_id_for_external_reference(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'doc_id' => 'external-doc-ref-123',
            'title' => 'External Document',
            'content' => 'Content',
        ]);

        // Assert
        $this->assertEquals('external-doc-ref-123', $document->doc_id);
    }

    public function test_stores_complex_metadata_structure(): void
    {
        // Arrange & Act
        $metadata = [
            'file_info' => [
                'name' => 'contract.pdf',
                'size' => 2048576,
                'type' => 'application/pdf',
            ],
            'analysis' => [
                'sentiment' => 'neutral',
                'entities' => ['Company A', 'Company B'],
                'key_terms' => ['liability', 'indemnification', 'termination'],
            ],
            'processing' => [
                'ocr_quality' => 0.98,
                'extracted_at' => '2024-01-20T10:30:00Z',
                'processed_by' => 'textract',
            ],
        ];

        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Document with Complex Metadata',
            'content' => 'Content',
            'metadata' => $metadata,
        ]);

        // Assert
        $this->assertEquals('contract.pdf', $document->metadata['file_info']['name']);
        $this->assertEquals(0.98, $document->metadata['processing']['ocr_quality']);
        $this->assertCount(3, $document->metadata['analysis']['key_terms']);
    }

    public function test_stores_multiple_tags_for_categorization(): void
    {
        // Arrange & Act
        $testCase = $this->createTestCase();
        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $testCase->id,
            'title' => 'Multi-Tagged Document',
            'content' => 'Content',
            'tags' => [
                'evidence',
                'financial',
                'exhibit-A',
                'Q4-2023',
                'confidential',
                'reviewed',
            ],
        ]);

        // Assert
        $this->assertCount(6, $document->tags);
        $this->assertContains('evidence', $document->tags);
        $this->assertContains('Q4-2023', $document->tags);
        $this->assertContains('reviewed', $document->tags);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $case = LegalCase::factory()->create();

        $document = CaseDocument::create([
            'id' => 'case-doc-123',
            'case_id' => $case->id,
            'doc_id' => 'doc-456',
            'title' => 'Witness Statement',
            'category' => 'evidence',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Document content...',
        ]);

        $this->assertEquals('case-doc-123', $document->id);
        $this->assertEquals($case->id, $document->case_id);
        $this->assertEquals('Witness Statement', $document->title);
        $this->assertEquals('evidence', $document->category);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        $document = CaseDocument::factory()->create(['id' => 'custom-doc-id']);

        $this->assertEquals('custom-doc-id', $document->id);
        $this->assertFalse($document->incrementing);
        $this->assertEquals('string', $document->getKeyType());
    }

    /** @test */
    public function it_belongs_to_legal_case()
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        $this->assertInstanceOf(LegalCase::class, $document->case);
        $this->assertEquals($case->id, $document->case->id);
    }

    /** @test */
    public function it_casts_tags_as_array()
    {
        $tags = ['evidence', 'witness', 'testimony'];
        $document = CaseDocument::factory()->create(['tags' => $tags]);

        $this->assertIsArray($document->tags);
        $this->assertCount(3, $document->tags);
        $this->assertContains('evidence', $document->tags);
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $metadata = [
            'page_count' => 15,
            'file_type' => 'pdf',
            'uploaded_by' => 'user-123',
        ];

        $document = CaseDocument::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($document->metadata);
        $this->assertEquals(15, $document->metadata['page_count']);
    }

    /** @test */
    public function it_casts_actual_as_array()
    {
        $actual = ['key' => 'value', 'data' => 'information'];
        $document = CaseDocument::factory()->create(['actual' => $actual]);

        $this->assertIsArray($document->actual);
        $this->assertEquals('value', $document->actual['key']);
    }

    /** @test */
    public function it_casts_embedding_vector_as_array()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $document = CaseDocument::factory()->withEmbedding()->create([
            'embedding_vector' => $embedding,
        ]);

        $this->assertIsArray($document->embedding_vector);
        $this->assertCount(1536, $document->embedding_vector);
    }

    /** @test */
    public function it_handles_different_document_categories()
    {
        $categories = ['evidence', 'witness', 'expert', 'pleading', 'motion'];

        foreach ($categories as $category) {
            $document = CaseDocument::factory()->create(['category' => $category]);
            $this->assertEquals($category, $document->category);
        }
    }

    /** @test */
    public function it_stores_chunk_information()
    {
        $document = CaseDocument::factory()->create([
            'chunk_index' => 5,
        ]);

        $this->assertEquals(5, $document->chunk_index);
    }

    /** @test */
    public function it_stores_embedding_metadata()
    {
        $document = CaseDocument::factory()->create([
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 1.0,
        ]);

        $this->assertEquals('openai', $document->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $document->embedding_model);
        $this->assertEquals(1536, $document->embedding_dimensions);
    }

    /** @test */
    public function it_stores_content_hash_for_deduplication()
    {
        $document = CaseDocument::factory()->create([
            'content_hash' => 'abc123def456',
        ]);

        $this->assertEquals('abc123def456', $document->content_hash);
    }

    /** @test */
    public function it_tracks_token_count()
    {
        $document = CaseDocument::factory()->create([
            'token_count' => 350,
        ]);

        $this->assertEquals(350, $document->token_count);
    }

    /** @test */
    public function it_handles_source_tracking()
    {
        $document = CaseDocument::factory()->create([
            'source' => 'upload',
            'source_id' => 'upload-123',
        ]);

        $this->assertEquals('upload', $document->source);
        $this->assertEquals('upload-123', $document->source_id);
    }

    /** @test */
    public function it_stores_author_information()
    {
        $document = CaseDocument::factory()->create([
            'author' => 'John Doe, Attorney',
        ]);

        $this->assertEquals('John Doe, Attorney', $document->author);
    }

    /** @test */
    public function it_handles_croatian_language()
    {
        $document = CaseDocument::factory()->create([
            'language' => 'hr',
            'title' => 'Izjava svjedoka',
            'content' => 'Svjedok izjavljuje sljedeće...',
        ]);

        $this->assertEquals('hr', $document->language);
        $this->assertStringContainsString('svjedok', $document->title);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.cases_documents' => 'custom_case_docs']);

        $document = new CaseDocument;

        $this->assertEquals('custom_case_docs', $document->getTable());
    }

    /** @test */
    public function it_handles_multiple_chunks_for_same_document()
    {
        $case = LegalCase::factory()->create();
        $docId = 'doc-multi-chunk';

        $chunk0 = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'doc_id' => $docId,
            'chunk_index' => 0,
            'content' => 'First chunk',
        ]);

        $chunk1 = CaseDocument::factory()->create([
            'case_id' => $case->id,
            'doc_id' => $docId,
            'chunk_index' => 1,
            'content' => 'Second chunk',
        ]);

        $this->assertEquals(0, $chunk0->chunk_index);
        $this->assertEquals(1, $chunk1->chunk_index);
        $this->assertEquals($docId, $chunk0->doc_id);
        $this->assertEquals($docId, $chunk1->doc_id);
    }

    /** @test */
    public function it_stores_document_category_evidence()
    {
        $document = CaseDocument::factory()->evidence()->create();

        $this->assertEquals('evidence', $document->category);
    }

    /** @test */
    public function it_stores_document_category_witness()
    {
        $document = CaseDocument::factory()->witness()->create();

        $this->assertEquals('witness', $document->category);
        $this->assertStringContainsString('Witness Statement', $document->title);
    }

    /** @test */
    public function it_handles_upload_reference()
    {
        $testCase = $this->createTestCase();
        $upload = CaseDocumentUpload::create([
            'id' => 'upload-789',
            'case_id' => $testCase->id,
            'doc_id' => 'doc-'.rand(1000, 9999),
            'local_path' => '/tmp/upload-789.pdf',
        ]);

        $document = CaseDocument::factory()->create([
            'upload_id' => $upload->id,
        ]);

        $this->assertEquals('upload-789', $document->upload_id);
    }
}
