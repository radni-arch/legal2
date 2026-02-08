<?php

namespace Tests\Unit\Models;

use App\Models\AiReasoningTrace;
use App\Models\CitationProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Test: Citation Provenance Model
 *
 * Tests for Sprint 1 User Story 1.1: Database Schema for Reasoning Traces
 * Citation provenance tracks the source and verification of legal citations.
 *
 * Acceptance Criteria:
 * - Can create citation provenance records
 * - Links to reasoning traces
 * - Stores citation metadata as JSONB
 * - Tracks verification status
 */
class CitationProvenanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Create basic citation provenance
     *
     * @test
     */
    public function test_can_create_citation_provenance()
    {
        // Arrange
        $citationData = [
            'citation_text' => 'ZKP Članak 9',
            'source_type' => 'law_database',
            'source_identifier' => 'zkp-9-2024',
            'citation_metadata' => [
                'law_name' => 'Zakon o kaznenom postupku',
                'article' => '9',
                'paragraph' => '1',
                'enacted' => '2024-01-01',
            ],
            'verification_status' => 'verified',
            'confidence_score' => 0.98,
        ];

        // Act
        $citation = CitationProvenance::create($citationData);

        // Assert
        $this->assertNotNull($citation->id);
        $this->assertNotNull($citation->citation_id); // UUID auto-generated
        $this->assertEquals('ZKP Članak 9', $citation->citation_text);
        $this->assertEquals('law_database', $citation->source_type);
        $this->assertEquals('zkp-9-2024', $citation->source_identifier);
        $this->assertEquals('verified', $citation->verification_status);
        $this->assertEquals(0.98, $citation->confidence_score);
        $this->assertIsArray($citation->citation_metadata);
    }

    /**
     * Test 2: Citation links to reasoning trace
     *
     * @test
     */
    public function test_citation_links_to_reasoning_trace()
    {
        // Arrange
        $trace = AiReasoningTrace::create([
            'agent_type' => 'ResearchAgent',
            'step_type' => 'legal_research',
            'operation' => 'find_citation',
            'reasoning' => 'Finding legal authority',
        ]);

        // Act
        $citation = CitationProvenance::create([
            'trace_id' => $trace->trace_id,
            'citation_text' => 'Ustav RH Članak 29',
            'source_type' => 'constitution',
            'source_identifier' => 'ustav-29',
            'verification_status' => 'verified',
        ]);

        // Assert
        $this->assertEquals($trace->trace_id, $citation->trace_id);
        $this->assertNotNull($citation->trace);
        $this->assertEquals($trace->id, $citation->trace->id);
    }

    /**
     * Test 3: Citation ID is UUID
     *
     * @test
     */
    public function test_citation_id_is_uuid()
    {
        // Act
        $citation = CitationProvenance::create([
            'citation_text' => 'Test Citation',
            'source_type' => 'test',
            'verification_status' => 'pending',
        ]);

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $citation->citation_id
        );
    }

    /**
     * Test 4: JSONB metadata stores complex structures
     *
     * @test
     */
    public function test_jsonb_metadata_stores_complex_citation_data()
    {
        // Arrange
        $complexMetadata = [
            'law_info' => [
                'official_name' => 'Kazneni zakon',
                'abbreviation' => 'KZ',
                'article' => '123',
                'paragraphs' => ['1', '2', '3'],
            ],
            'context' => [
                'related_articles' => ['121', '122', '124'],
                'case_law' => ['VSRH-123/2024', 'VSRH-456/2024'],
            ],
            'retrieval' => [
                'database' => 'narodne-novine',
                'retrieved_at' => '2025-11-10T10:00:00Z',
                'url' => 'https://example.com/kz-123',
            ],
        ];

        // Act
        $citation = CitationProvenance::create([
            'citation_text' => 'KZ Članak 123',
            'source_type' => 'criminal_code',
            'source_identifier' => 'kz-123',
            'citation_metadata' => $complexMetadata,
            'verification_status' => 'verified',
        ]);

        // Assert
        $this->assertEquals($complexMetadata, $citation->citation_metadata);
        $this->assertIsArray($citation->citation_metadata);
        $this->assertEquals('Kazneni zakon', $citation->citation_metadata['law_info']['official_name']);
    }

    /**
     * Test 5: Verification status tracking
     *
     * @test
     */
    public function test_verification_status_can_be_tracked()
    {
        // Act
        $citation = CitationProvenance::create([
            'citation_text' => 'Test Law Article 1',
            'source_type' => 'law',
            'verification_status' => 'pending',
        ]);

        // Assert
        $this->assertEquals('pending', $citation->verification_status);

        // Update verification status
        $citation->update(['verification_status' => 'verified']);
        $this->assertEquals('verified', $citation->fresh()->verification_status);
    }

    /**
     * Test 6: Confidence score is decimal
     *
     * @test
     */
    public function test_confidence_score_is_decimal()
    {
        // Act
        $citation = CitationProvenance::create([
            'citation_text' => 'Law Article X',
            'source_type' => 'law',
            'verification_status' => 'verified',
            'confidence_score' => 0.87,
        ]);

        // Assert
        $this->assertIsFloat($citation->confidence_score);
        $this->assertEquals(0.87, $citation->confidence_score);
    }

    /**
     * Test 7: Timestamps are automatically set
     *
     * @test
     */
    public function test_timestamps_are_set()
    {
        // Act
        $citation = CitationProvenance::create([
            'citation_text' => 'Test Citation',
            'source_type' => 'test',
            'verification_status' => 'pending',
        ]);

        // Assert
        $this->assertNotNull($citation->created_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $citation->created_at);
    }
}
