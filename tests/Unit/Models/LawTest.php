<?php

namespace Tests\Unit\Models;

use App\Models\IngestedLaw;
use App\Models\Law;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class LawTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Get default embedding vector for tests
     */
    protected function getDefaultEmbeddingVector(): array
    {
        return array_fill(0, 1536, 0.0);
    }

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-chunk-001',
            'title' => 'Test Law Chunk',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Law content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertIsString($law->id);
        $this->assertNotEmpty($law->id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'law-full-attrs',
            'title' => 'Zakon o radu - Članak 1-10',
            'law_number' => 'NN 93/2014',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'promulgation_date' => '2014-07-25',
            'effective_date' => '2014-08-01',
            'version' => '2.1',
            'chapter' => 'Chapter I',
            'section' => 'General Provisions',
            'tags' => ['labor', 'employment', 'rights'],
            'source_url' => 'https://www.zakon.hr/z/307',
            'chunk_index' => 0,
            'content' => 'Članak 1. Ovim zakonom...',
            'metadata' => ['source' => 'NN'],
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
            'embedding_norm' => 0.9876,
            'content_hash' => hash('sha256', 'content'),
            'token_count' => 250,
        ]);

        // Assert
        $this->assertEquals('Zakon o radu - Članak 1-10', $law->title);
        $this->assertEquals('NN 93/2014', $law->law_number);
        $this->assertEquals('HR', $law->jurisdiction);
        $this->assertEquals('Chapter I', $law->chapter);
    }

    public function test_belongs_to_ingested_law(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'parent-law-001',
            'title' => 'Parent Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'child-chunk',
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Law Chunk',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Chunk content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        // Act
        $parent = $law->ingestedLaw;

        // Assert
        $this->assertInstanceOf(IngestedLaw::class, $parent);
        $this->assertEquals($ingestedLaw->id, $parent->id);
        $this->assertEquals('Parent Law', $parent->title);
    }

    public function test_casts_tags_as_array(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'tags-test',
            'title' => 'Tagged Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'tags' => ['civil', 'contract', 'obligations', 'commercial'],
            'content' => 'Content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertIsArray($law->tags);
        $this->assertCount(4, $law->tags);
        $this->assertContains('civil', $law->tags);
        $this->assertContains('commercial', $law->tags);
    }

    public function test_casts_metadata_as_array(): void
    {
        // Arrange & Act
        $metadata = [
            'source' => 'NN',
            'article_range' => '1-50',
            'total_articles' => 50,
            'has_tables' => true,
        ];

        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'metadata-test',
            'title' => 'Law with Metadata',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content',
            'metadata' => $metadata,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertIsArray($law->metadata);
        $this->assertEquals('NN', $law->metadata['source']);
        $this->assertEquals(50, $law->metadata['total_articles']);
        $this->assertTrue($law->metadata['has_tables']);
    }

    public function test_casts_embedding_vector_as_array(): void
    {
        // Arrange & Act
        $embedding = array_fill(0, 1536, 0.123);

        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'embedding-test',
            'title' => 'Law with Embedding',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content',
            'embedding_vector' => $embedding,
        ]);

        // Assert
        $this->assertIsArray($law->embedding_vector);
        $this->assertCount(1536, $law->embedding_vector);
        $this->assertEquals(0.123, $law->embedding_vector[0]);
    }

    public function test_casts_dates_correctly(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'dates-test',
            'title' => 'Law with Dates',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content',
            'promulgation_date' => '2023-01-15',
            'effective_date' => '2023-02-01',
            'repeal_date' => '2025-12-31',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->promulgation_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->effective_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->repeal_date);
        $this->assertEquals('2023-01-15', $law->promulgation_date->toDateString());
    }

    public function test_stores_embedding_metadata(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'embed-meta',
            'title' => 'Law with Embedding Metadata',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 0.9987,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals('openai', $law->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $law->embedding_model);
        $this->assertEquals(1536, $law->embedding_dimensions);
        $this->assertEquals(0.9987, $law->embedding_norm);
    }

    public function test_stores_content_hash_and_token_count(): void
    {
        // Arrange
        $content = 'Sample law content for hashing';
        $hash = hash('sha256', $content);

        // Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'hash-test',
            'title' => 'Law with Hash',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => $content,
            'content_hash' => $hash,
            'token_count' => 125,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals($hash, $law->content_hash);
        $this->assertEquals(64, strlen($law->content_hash)); // SHA-256 produces 64 hex chars
        $this->assertEquals(125, $law->token_count);
    }

    public function test_handles_chunk_index_for_split_laws(): void
    {
        // Arrange
        $ingestedLaw = IngestedLaw::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'chunked-law',
            'title' => 'Large Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'ingested_at' => now(),
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Act
        $chunk0 = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'chunk-0',
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Large Law - Part 1',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'First chunk',
            'chunk_index' => 0,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        $chunk1 = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'chunk-1',
            'ingested_law_id' => $ingestedLaw->id,
            'title' => 'Large Law - Part 2',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Second chunk',
            'chunk_index' => 1,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals(0, $chunk0->chunk_index);
        $this->assertEquals(1, $chunk1->chunk_index);
    }

    public function test_stores_croatian_content_correctly(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'croatian-content',
            'title' => 'Zakon o obveznim odnosima',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Članak 1. Građansko pravo uređuje...',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        // Assert
        $this->assertStringContainsString('Članak', $law->content);
        $this->assertStringContainsString('Građansko', $law->content);
        $this->assertStringContainsString('uređuje', $law->content);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'minimal-law',
            'title' => 'Minimal Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Minimal content',
            'promulgation_date' => null,
            'effective_date' => null,
            'repeal_date' => null,
            'chapter' => null,
            'section' => null,
            'tags' => null,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertNull($law->promulgation_date);
        $this->assertNull($law->effective_date);
        $this->assertNull($law->repeal_date);
        $this->assertNull($law->chapter);
        $this->assertNull($law->section);
        $this->assertNull($law->tags);
        // embedding_vector is now nullable (made nullable in migration 2025_11_04_080708)
        $this->assertNull($law->embedding_vector);
    }

    public function test_supports_version_tracking(): void
    {
        // Arrange & Act
        $v1 = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'versioned-law-v1',
            'title' => 'Versioned Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Version 1 content',
            'version' => '1.0',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        $v2 = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'versioned-law-v2',
            'title' => 'Versioned Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Version 2 content with amendments',
            'version' => '2.0',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals('1.0', $v1->version);
        $this->assertEquals('2.0', $v2->version);
    }

    public function test_stores_chapter_and_section(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'structured-law',
            'title' => 'Structured Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Structured content',
            'chapter' => 'Chapter III: Procedural Rules',
            'section' => 'Section 2: Evidence',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals('Chapter III: Procedural Rules', $law->chapter);
        $this->assertEquals('Section 2: Evidence', $law->section);
    }

    public function test_handles_repeal_date_for_obsolete_laws(): void
    {
        // Arrange & Act
        $activeLaw = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'active-law',
            'title' => 'Active Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Active content',
            'effective_date' => '2020-01-01',
            'repeal_date' => null,
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        $repealedLaw = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'repealed-law',
            'title' => 'Repealed Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Repealed content',
            'effective_date' => '2015-01-01',
            'repeal_date' => '2023-12-31',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertNull($activeLaw->repeal_date);
        $this->assertNotNull($repealedLaw->repeal_date);
        $this->assertEquals('2023-12-31', $repealedLaw->repeal_date->toDateString());
    }

    public function test_supports_different_jurisdictions(): void
    {
        // Arrange & Act
        $national = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'national-law',
            'title' => 'National Law',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'National content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        $eu = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'eu-reg',
            'title' => 'EU Regulation',
            'jurisdiction' => 'EU',
            'country' => 'EU',
            'language' => 'en',
            'content' => 'EU content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        $regional = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'regional-law',
            'title' => 'Regional Law',
            'jurisdiction' => 'regional',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Regional content',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        // Assert
        $this->assertEquals('HR', $national->jurisdiction);
        $this->assertEquals('EU', $eu->jurisdiction);
        $this->assertEquals('regional', $regional->jurisdiction);
    }

    public function test_stores_source_url(): void
    {
        // Arrange & Act
        $law = Law::create([
            'id' => Str::ulid()->toString(),
            'doc_id' => 'url-law',
            'title' => 'Law with URL',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Content',
            'source_url' => 'https://www.zakon.hr/z/307/Zakon-o-obveznim-odnosima',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),
        ]);

        // Assert
        $this->assertEquals('https://www.zakon.hr/z/307/Zakon-o-obveznim-odnosima', $law->source_url);
        $this->assertStringStartsWith('https://', $law->source_url);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $law = Law::create([
            'id' => 'law-123',
            'doc_id' => 'doc-456',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'country' => 'Croatia',
            'language' => 'hr',
            'chunk_index' => 0,
            'content' => 'Članak 1. Ovaj zakon uređuje kazneni postupak...',
            'embedding_vector' => $this->getDefaultEmbeddingVector(),        ]);

        $this->assertEquals('law-123', $law->id);
        $this->assertEquals('Zakon o kaznenom postupku', $law->title);
        $this->assertEquals('NN 152/08', $law->law_number);
        $this->assertEquals('HR', $law->jurisdiction);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        $law = Law::factory()->create(['id' => 'custom-law-id']);

        $this->assertEquals('custom-law-id', $law->id);
        $this->assertFalse($law->incrementing);
        $this->assertEquals('string', $law->getKeyType());
    }

    /** @test */
    public function it_belongs_to_ingested_law()
    {
        $ingestedLaw = IngestedLaw::factory()->create();
        $law = Law::factory()->create(['ingested_law_id' => $ingestedLaw->id]);

        $this->assertInstanceOf(IngestedLaw::class, $law->ingestedLaw);
        $this->assertEquals($ingestedLaw->id, $law->ingestedLaw->id);
    }

    /** @test */
    public function it_handles_croatian_law_numbers()
    {
        $lawNumbers = [
            'NN 152/08',
            'NN 91/12',
            'NN 121/11',
            'NN 71/10',
        ];

        foreach ($lawNumbers as $number) {
            $law = Law::factory()->create(['law_number' => $number]);
            $this->assertEquals($number, $law->law_number);
        }
    }

    /** @test */
    public function it_handles_croatian_unicode_characters()
    {
        $law = Law::factory()->create([
            'title' => 'Kazneni zakon - članak o krivnji i kazni',
            'content' => 'Članak 291. Tko počini krivično djelo...',
        ]);

        $this->assertStringContainsString('članak', $law->title);
        $this->assertStringContainsString('Članak', $law->content);
    }

    /** @test */
    public function it_casts_tags_as_array()
    {
        $tags = ['criminal', 'procedure', 'prosecution'];
        $law = Law::factory()->create(['tags' => $tags]);

        $this->assertIsArray($law->tags);
        $this->assertCount(3, $law->tags);
        $this->assertContains('criminal', $law->tags);
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $metadata = [
            'category' => 'criminal',
            'subcategory' => 'procedure',
            'effective_version' => '2024',
        ];

        $law = Law::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($law->metadata);
        $this->assertEquals('criminal', $law->metadata['category']);
    }

    /** @test */
    public function it_casts_embedding_vector_as_array()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $law = Law::factory()->create(['embedding_vector' => $embedding]);

        $this->assertIsArray($law->embedding_vector);
        $this->assertCount(1536, $law->embedding_vector);
    }

    /** @test */
    public function it_casts_date_fields_correctly()
    {
        $law = Law::factory()->create([
            'promulgation_date' => '2008-12-01',
            'effective_date' => '2009-01-01',
            'repeal_date' => null,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->promulgation_date);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $law->effective_date);
        $this->assertNull($law->repeal_date);
    }

    /** @test */
    public function it_handles_law_versioning()
    {
        $law = Law::factory()->create([
            'law_number' => 'NN 152/08',
            'version' => '01',
            'promulgation_date' => '2008-12-01',
            'effective_date' => '2009-01-01',
        ]);

        $this->assertEquals('01', $law->version);
    }

    /** @test */
    public function it_handles_repealed_laws()
    {
        $law = Law::factory()->create([
            'repeal_date' => now()->subYear(),
        ]);

        $this->assertNotNull($law->repeal_date);
        $this->assertTrue($law->repeal_date->isPast());
    }

    /** @test */
    public function it_stores_chapter_and_section_information()
    {
        $law = Law::factory()->create([
            'chapter' => 'Poglavlje I - Opće odredbe',
            'section' => 'Odjeljak 1 - Osnovne odredbe',
        ]);

        $this->assertEquals('Poglavlje I - Opće odredbe', $law->chapter);
        $this->assertEquals('Odjeljak 1 - Osnovne odredbe', $law->section);
    }

    /** @test */
    public function it_stores_source_url()
    {
        $law = Law::factory()->create([
            'source_url' => 'https://narodne-novine.nn.hr/clanci/sluzbeni/2008_12_152_4036.html',
        ]);

        $this->assertStringContainsString('narodne-novine', $law->source_url);
    }

    /** @test */
    public function it_handles_chunk_index_for_large_laws()
    {
        $law1 = Law::factory()->create([
            'doc_id' => 'doc-large-law',
            'chunk_index' => 0,
        ]);

        $law2 = Law::factory()->create([
            'doc_id' => 'doc-large-law',
            'chunk_index' => 1,
        ]);

        $this->assertEquals(0, $law1->chunk_index);
        $this->assertEquals(1, $law2->chunk_index);
    }

    /** @test */
    public function it_stores_embedding_metadata()
    {
        $law = Law::factory()->create([
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 1.0,
        ]);

        $this->assertEquals('openai', $law->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $law->embedding_model);
        $this->assertEquals(1536, $law->embedding_dimensions);
    }

    /** @test */
    public function it_stores_content_hash_for_deduplication()
    {
        $content = 'Članak 291. Zakon o kaznenom postupku...';
        $hash = md5($content);

        $law = Law::factory()->create([
            'content' => $content,
            'content_hash' => $hash,
        ]);

        $this->assertEquals($hash, $law->content_hash);
    }

    /** @test */
    public function it_tracks_token_count()
    {
        $law = Law::factory()->create([
            'content' => 'Legal content here...',
            'token_count' => 250,
        ]);

        $this->assertEquals(250, $law->token_count);
    }

    /** @test */
    public function it_handles_different_jurisdictions()
    {
        $hrLaw = Law::factory()->create(['jurisdiction' => 'HR', 'country' => 'Croatia']);
        $euLaw = Law::factory()->create(['jurisdiction' => 'EU', 'country' => 'European Union']);

        $this->assertEquals('HR', $hrLaw->jurisdiction);
        $this->assertEquals('EU', $euLaw->jurisdiction);
    }

    /** @test */
    public function it_handles_multiple_languages()
    {
        $hrLaw = Law::factory()->create(['language' => 'hr']);
        $enLaw = Law::factory()->create(['language' => 'en']);

        $this->assertEquals('hr', $hrLaw->language);
        $this->assertEquals('en', $enLaw->language);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.laws' => 'custom_laws_table']);

        $law = new Law;

        $this->assertEquals('custom_laws_table', $law->getTable());
    }
}
