<?php

namespace Tests\Unit\Models;

use App\Models\AgentVectorMemory;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentVectorMemoryTest extends TestCase
{
    use UsesTestDatabase;

    public function test_uses_string_primary_key(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'research-agent',
            'content' => 'Sample memory content',
        ]);

        // Assert
        $this->assertIsString($memory->id);
        $this->assertNotEmpty($memory->id);
        $this->assertFalse($memory->incrementing);
        $this->assertEquals('string', $memory->getKeyType());
    }

    public function test_has_correct_fillable_attributes(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'research-agent',
            'namespace' => 'legal-research',
            'content' => 'Important legal precedent information',
            'metadata' => ['source' => 'case-law', 'date' => '2024-01-15'],
            'source' => 'court-decision',
            'source_id' => 'case-12345',
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 0.9876,
            'content_hash' => hash('sha256', 'content'),
            'token_count' => 250,
        ]);

        // Assert
        $this->assertEquals('research-agent', $memory->agent_name);
        $this->assertEquals('legal-research', $memory->namespace);
        $this->assertEquals('Important legal precedent information', $memory->content);
        $this->assertEquals('court-decision', $memory->source);
        $this->assertEquals('case-12345', $memory->source_id);
    }

    public function test_casts_metadata_as_array(): void
    {
        // Arrange & Act
        $metadata = [
            'source' => 'legal-database',
            'category' => 'precedent',
            'jurisdiction' => 'HR',
            'confidence' => 0.95,
        ];

        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Test content',
            'metadata' => $metadata,
        ]);

        // Assert
        $this->assertIsArray($memory->metadata);
        $this->assertEquals('legal-database', $memory->metadata['source']);
        $this->assertEquals('precedent', $memory->metadata['category']);
        $this->assertEquals(0.95, $memory->metadata['confidence']);
    }

    public function test_casts_embedding_vector_as_array(): void
    {
        // Arrange & Act
        $embedding = array_fill(0, 1536, 0.123);

        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Content with embedding',
            'embedding_vector' => $embedding,
        ]);

        // Update the embedding_vector after creation to bypass the creating event default
        $memory->update(['embedding_vector' => $embedding]);
        $memory->refresh();

        // Assert
        $this->assertIsArray($memory->embedding_vector);
        $this->assertCount(1536, $memory->embedding_vector);
        $this->assertEqualsWithDelta(0.123, $memory->embedding_vector[0], 0.001);
    }

    public function test_stores_agent_name(): void
    {
        // Arrange & Act
        $researchAgent = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'research-agent',
            'content' => 'Research memory',
        ]);

        $analysisAgent = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'analysis-agent',
            'content' => 'Analysis memory',
        ]);

        // Assert
        $this->assertEquals('research-agent', $researchAgent->agent_name);
        $this->assertEquals('analysis-agent', $analysisAgent->agent_name);
    }

    public function test_stores_namespace(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'namespace' => 'contract-analysis',
            'content' => 'Memory content',
        ]);

        // Assert
        $this->assertEquals('contract-analysis', $memory->namespace);
    }

    public function test_stores_chunk_index_for_long_content(): void
    {
        // Arrange & Act
        $chunk0 = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'First chunk of long content',
            'chunk_index' => 0,
        ]);

        $chunk1 = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Second chunk of long content',
            'chunk_index' => 1,
        ]);

        // Assert
        $this->assertEquals(0, $chunk0->chunk_index);
        $this->assertEquals(1, $chunk1->chunk_index);
    }

    public function test_stores_embedding_metadata(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-large',
            'embedding_dimensions' => 3072,
            'embedding_norm' => 0.9987,
        ]);

        // Assert
        $this->assertEquals('openai', $memory->embedding_provider);
        $this->assertEquals('text-embedding-3-large', $memory->embedding_model);
        $this->assertEquals(3072, $memory->embedding_dimensions);
        $this->assertEquals(0.9987, $memory->embedding_norm);
    }

    public function test_stores_content_hash_and_token_count(): void
    {
        // Arrange
        $content = 'Sample agent memory content for hashing';
        $hash = hash('sha256', $content);

        // Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => $content,
            'content_hash' => $hash,
            'token_count' => 150,
        ]);

        // Assert
        $this->assertEquals($hash, $memory->content_hash);
        $this->assertEquals(64, strlen($memory->content_hash)); // SHA-256 produces 64 hex chars
        $this->assertEquals(150, $memory->token_count);
    }

    public function test_stores_source_information(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Memory from case document',
            'source' => 'case-document',
            'source_id' => 'doc-uuid-12345',
        ]);

        // Assert
        $this->assertEquals('case-document', $memory->source);
        $this->assertEquals('doc-uuid-12345', $memory->source_id);
    }

    public function test_handles_null_optional_fields(): void
    {
        // Arrange & Act
        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Minimal memory',
            'metadata' => null,
            'source' => null,
            'source_id' => null,
        ]);

        // Refresh to apply casts
        $memory->refresh();

        // Assert
        // Note: namespace is auto-filled with 'default' by model boot event
        $this->assertEquals('default', $memory->namespace);
        $this->assertNull($memory->metadata);
        $this->assertNull($memory->source);
        $this->assertNull($memory->source_id);
        // Note: embedding_vector is auto-filled by model boot event with default values
        $this->assertIsArray($memory->embedding_vector);
    }

    public function test_stores_complex_metadata_structure(): void
    {
        // Arrange & Act
        $metadata = [
            'extraction' => [
                'method' => 'automated',
                'confidence' => 0.95,
                'timestamp' => '2024-01-20T10:30:00Z',
            ],
            'context' => [
                'case_id' => 'case-123',
                'document_type' => 'contract',
                'relevance_score' => 0.88,
            ],
            'tags' => ['important', 'precedent', 'contract-law'],
        ];

        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Memory with complex metadata',
            'metadata' => $metadata,
        ]);

        // Assert
        $this->assertEquals('automated', $memory->metadata['extraction']['method']);
        $this->assertEquals(0.95, $memory->metadata['extraction']['confidence']);
        $this->assertEquals('case-123', $memory->metadata['context']['case_id']);
        $this->assertCount(3, $memory->metadata['tags']);
    }

    public function test_can_query_by_agent_name(): void
    {
        // Arrange
        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'research-agent',
            'content' => 'Research memory 1',
        ]);

        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'research-agent',
            'content' => 'Research memory 2',
        ]);

        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'analysis-agent',
            'content' => 'Analysis memory',
        ]);

        // Act
        $researchMemories = AgentVectorMemory::where('agent_name', 'research-agent')->get();
        $analysisMemories = AgentVectorMemory::where('agent_name', 'analysis-agent')->get();

        // Assert
        $this->assertCount(2, $researchMemories);
        $this->assertCount(1, $analysisMemories);
    }

    public function test_can_query_by_namespace(): void
    {
        // Arrange
        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'namespace' => 'contract-law',
            'content' => 'Contract memory 1',
        ]);

        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'namespace' => 'contract-law',
            'content' => 'Contract memory 2',
        ]);

        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'namespace' => 'criminal-law',
            'content' => 'Criminal memory',
        ]);

        // Act
        $contractMemories = AgentVectorMemory::where('namespace', 'contract-law')->get();
        $criminalMemories = AgentVectorMemory::where('namespace', 'criminal-law')->get();

        // Assert
        $this->assertCount(2, $contractMemories);
        $this->assertCount(1, $criminalMemories);
    }

    public function test_can_query_by_source(): void
    {
        // Arrange
        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Memory from case',
            'source' => 'case-document',
        ]);

        AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Memory from law',
            'source' => 'law-database',
        ]);

        // Act
        $caseMemories = AgentVectorMemory::where('source', 'case-document')->get();
        $lawMemories = AgentVectorMemory::where('source', 'law-database')->get();

        // Assert
        $this->assertCount(1, $caseMemories);
        $this->assertCount(1, $lawMemories);
    }

    public function test_stores_long_form_content(): void
    {
        // Arrange & Act
        $longContent = str_repeat('This is a long piece of legal text that an agent needs to remember. ', 50);

        $memory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => $longContent,
        ]);

        // Assert
        $this->assertEquals($longContent, $memory->content);
        $this->assertGreaterThan(1000, strlen($memory->content));
    }

    public function test_uses_configurable_table_name(): void
    {
        // Arrange & Act
        $memory = new AgentVectorMemory;

        // Assert
        $this->assertEquals(
            config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories'),
            $memory->getTable()
        );
    }

    public function test_stores_different_embedding_providers(): void
    {
        // Arrange & Act
        $openaiMemory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'OpenAI embedded content',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
        ]);

        $cohereMemory = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Cohere embedded content',
            'embedding_provider' => 'cohere',
            'embedding_model' => 'embed-english-v3.0',
        ]);

        // Assert
        $this->assertEquals('openai', $openaiMemory->embedding_provider);
        $this->assertEquals('cohere', $cohereMemory->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $openaiMemory->embedding_model);
        $this->assertEquals('embed-english-v3.0', $cohereMemory->embedding_model);
    }

    public function test_supports_both_embedding_columns(): void
    {
        // Arrange & Act
        $embedding = array_fill(0, 1536, 0.5);

        // Test with embedding_vector (pgvector with 1536 dimensions)
        $memory1 = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Content 1',
            'embedding_vector' => $embedding,
        ]);

        // Test with another memory using same dimensions
        $memory2 = AgentVectorMemory::create([
            'id' => Str::ulid()->toString(),
            'agent_name' => 'test-agent',
            'content' => 'Content 2',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, 0.3),
        ]);

        // Assert
        $this->assertIsArray($memory1->embedding_vector);
        $this->assertCount(1536, $memory1->embedding_vector);
        $this->assertIsArray($memory2->embedding_vector);
        $this->assertEquals(1536, $memory2->embedding_dimensions);
        $this->assertCount(1536, $memory2->embedding_vector);

    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $memory = AgentVectorMemory::create([
            'id' => 'memory-123',
            'agent_name' => 'researcher',
            'namespace' => 'facts',
            'content' => 'Legal precedent findings from case study...',
            'metadata' => ['importance' => 'high'],
            'source' => 'agent_output',
            'chunk_index' => 0,
            'token_count' => 150,
        ]);

        $this->assertEquals('memory-123', $memory->id);
        $this->assertEquals('researcher', $memory->agent_name);
        $this->assertEquals('facts', $memory->namespace);
        $this->assertEquals('agent_output', $memory->source);
    }

    /** @test */
    public function it_uses_string_primary_key()
    {
        $memory = AgentVectorMemory::factory()->create(['id' => 'custom-memory-id']);

        $this->assertEquals('custom-memory-id', $memory->id);
        $this->assertFalse($memory->incrementing);
        $this->assertEquals('string', $memory->getKeyType());
    }

    /** @test */
    public function it_casts_metadata_as_array()
    {
        $metadata = [
            'importance' => 'high',
            'created_by' => 'researcher',
            'tags' => ['criminal', 'procedure'],
        ];

        $memory = AgentVectorMemory::factory()->create(['metadata' => $metadata]);

        $this->assertIsArray($memory->metadata);
        $this->assertEquals('high', $memory->metadata['importance']);
        $this->assertCount(2, $memory->metadata['tags']);
    }

    /** @test */
    public function it_casts_embedding_vector_as_array()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $memory = AgentVectorMemory::factory()->withEmbedding()->create([
            'embedding_vector' => $embedding,
        ]);

        $this->assertIsArray($memory->embedding_vector);
        $this->assertCount(1536, $memory->embedding_vector);
    }

    /** @test */
    public function it_handles_different_agent_names()
    {
        $agentNames = ['researcher', 'analyst', 'reviewer', 'synthesizer'];

        foreach ($agentNames as $name) {
            $memory = AgentVectorMemory::factory()->create(['agent_name' => $name]);
            $this->assertEquals($name, $memory->agent_name);
        }
    }

    /** @test */
    public function it_handles_different_namespaces()
    {
        $namespaces = ['facts', 'insights', 'precedents', 'strategies'];

        foreach ($namespaces as $namespace) {
            $memory = AgentVectorMemory::factory()->create(['namespace' => $namespace]);
            $this->assertEquals($namespace, $memory->namespace);
        }
    }

    /** @test */
    public function it_stores_content()
    {
        $content = 'Legal precedent from Croatian Supreme Court regarding criminal procedure...';
        $memory = AgentVectorMemory::factory()->create(['content' => $content]);

        $this->assertEquals($content, $memory->content);
    }

    /** @test */
    public function it_tracks_source_and_source_id()
    {
        $memory = AgentVectorMemory::factory()->create([
            'source' => 'external_research',
            'source_id' => 'research-doc-456',
        ]);

        $this->assertEquals('external_research', $memory->source);
        $this->assertEquals('research-doc-456', $memory->source_id);
    }

    /** @test */
    public function it_handles_chunk_index_for_large_memories()
    {
        $memory1 = AgentVectorMemory::factory()->create([
            'agent_name' => 'researcher',
            'namespace' => 'facts',
            'chunk_index' => 0,
        ]);

        $memory2 = AgentVectorMemory::factory()->create([
            'agent_name' => 'researcher',
            'namespace' => 'facts',
            'chunk_index' => 1,
        ]);

        $this->assertEquals(0, $memory1->chunk_index);
        $this->assertEquals(1, $memory2->chunk_index);
    }

    /** @test */
    public function it_stores_embedding_provider_and_model()
    {
        $memory = AgentVectorMemory::factory()->create([
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => 1.0,
        ]);

        $this->assertEquals('openai', $memory->embedding_provider);
        $this->assertEquals('text-embedding-3-small', $memory->embedding_model);
        $this->assertEquals(1536, $memory->embedding_dimensions);
    }

    /** @test */
    public function it_stores_content_hash_for_deduplication()
    {
        $memory = AgentVectorMemory::factory()->create([
            'content_hash' => 'abc123def456',
        ]);

        $this->assertEquals('abc123def456', $memory->content_hash);
    }

    /** @test */
    public function it_tracks_token_count()
    {
        $memory = AgentVectorMemory::factory()->create([
            'token_count' => 250,
        ]);

        $this->assertEquals(250, $memory->token_count);
    }

    /** @test */
    public function it_uses_configurable_table_name()
    {
        config(['vizra-adk.tables.agent_vector_memories' => 'custom_memories']);

        $memory = new AgentVectorMemory;

        $this->assertEquals('custom_memories', $memory->getTable());
    }

    /** @test */
    public function it_handles_croatian_content()
    {
        $memory = AgentVectorMemory::factory()->create([
            'content' => 'Zakon o kaznenom postupku - članak 291 o primjeni mjera opreza',
        ]);

        $this->assertStringContainsString('članak', $memory->content);
        $this->assertStringContainsString('primjeni', $memory->content);
    }

    /** @test */
    public function it_can_be_scoped_by_agent_using_factory()
    {
        $memory = AgentVectorMemory::factory()->forAgent('researcher')->create();

        $this->assertEquals('researcher', $memory->agent_name);
    }

    /** @test */
    public function it_can_be_scoped_by_namespace_using_factory()
    {
        $memory = AgentVectorMemory::factory()->inNamespace('precedents')->create();

        $this->assertEquals('precedents', $memory->namespace);
    }

    /** @test */
    public function it_can_have_embedding_using_factory()
    {
        $memory = AgentVectorMemory::factory()->withEmbedding()->create();

        $this->assertNotNull($memory->embedding_vector);
        $this->assertIsArray($memory->embedding_vector);
    }

    /** @test */
    public function it_handles_different_source_types()
    {
        $sources = ['user_input', 'agent_output', 'external_research'];

        foreach ($sources as $source) {
            $memory = AgentVectorMemory::factory()->create(['source' => $source]);
            $this->assertEquals($source, $memory->source);
        }
    }

    /** @test */
    public function it_stores_comprehensive_metadata()
    {
        $memory = AgentVectorMemory::factory()->create([
            'metadata' => [
                'importance' => 'high',
                'created_by' => 'researcher',
                'tags' => ['criminal', 'law', 'procedure'],
                'confidence' => 0.95,
                'verified' => true,
            ],
        ]);

        $this->assertEquals('high', $memory->metadata['importance']);
        $this->assertTrue($memory->metadata['verified']);
        $this->assertEquals(0.95, $memory->metadata['confidence']);
    }

    /** @test */
    public function it_supports_both_embedding_and_embedding_vector_fields()
    {
        // Test embedding_vector with JSON storage (pgvector not installed)
        $embedding = array_fill(0, 1536, 0.5);

        $memory = AgentVectorMemory::factory()->create([
            'embedding_vector' => $embedding,
        ]);

        $this->assertNotNull($memory->embedding_vector);
        $this->assertIsArray($memory->embedding_vector);
        $this->assertCount(1536, $memory->embedding_vector);
    }

    /** @test */
    public function it_handles_long_content()
    {
        $longContent = str_repeat('Croatian legal content with detailed analysis. ', 100);
        $memory = AgentVectorMemory::factory()->create([
            'content' => $longContent,
            'token_count' => 1000,
        ]);

        $this->assertEquals($longContent, $memory->content);
        $this->assertEquals(1000, $memory->token_count);
    }
}
