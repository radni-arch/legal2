<?php

namespace Tests\Unit\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\CrossReferenceAnalyzer;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CrossReferenceAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    private CrossReferenceAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new CrossReferenceAnalyzer();
    }

    /**
     * @test
     */
    public function it_returns_empty_results_when_no_entity_analyses_exist(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->analyzer->analyze($case->id);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEmpty($result['results']['cross_references']);
        $this->assertEquals(0, $result['results']['total_cross_refs']);
    }

    /**
     * @test
     */
    public function it_finds_case_numbers_appearing_in_multiple_documents(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Both documents reference the same case number
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'P-123/2024'],
                        ['case_number' => 'P-456/2024'],
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'P-123/2024'], // Same as doc1
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // Only P-123/2024 appears in both documents
        $this->assertEquals(1, $result['results']['total_cross_refs']);

        $crossRef = $result['results']['cross_references'][0];
        $this->assertEquals('case_numbers', $crossRef['type']);
        $this->assertEquals('P-123/2024', $crossRef['value']);
        $this->assertCount(2, $crossRef['document_ids']);
        $this->assertEquals(2, $crossRef['total_mentions']);
    }

    /**
     * @test
     */
    public function it_finds_law_references_appearing_in_multiple_documents(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc3 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Law referenced in all three documents
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [],
                    'laws' => [
                        ['law' => 'Zakon o obveznim odnosima'],
                    ],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [],
                    'laws' => [
                        ['law' => 'Zakon o obveznim odnosima'],
                    ],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc3->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [],
                    'laws' => [
                        ['law' => 'Zakon o obveznim odnosima'],
                    ],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertEquals(1, $result['results']['total_cross_refs']);

        $crossRef = $result['results']['cross_references'][0];
        $this->assertEquals('laws', $crossRef['type']);
        $this->assertEquals('Zakon o obveznim odnosima', $crossRef['value']);
        $this->assertCount(3, $crossRef['document_ids']);
        $this->assertEquals(3, $crossRef['total_mentions']);
    }

    /**
     * @test
     */
    public function it_does_not_include_entities_appearing_in_only_one_document(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Each document has unique entities
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'P-111/2024'],
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'P-222/2024'], // Different from doc1
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // No cross-references since each entity appears in only one document
        $this->assertEquals(0, $result['results']['total_cross_refs']);
        $this->assertEmpty($result['results']['cross_references']);
    }

    /**
     * @test
     */
    public function it_sorts_cross_references_by_document_count_descending(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc3 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Entity appearing in 2 documents
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'TWO-DOC-REF'],
                    ],
                    'laws' => [],
                    'courts' => [
                        ['court' => 'THREE-DOC-COURT'],
                    ],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'TWO-DOC-REF'],
                    ],
                    'laws' => [],
                    'courts' => [
                        ['court' => 'THREE-DOC-COURT'],
                    ],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc3->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [],
                    'laws' => [],
                    'courts' => [
                        ['court' => 'THREE-DOC-COURT'],
                    ],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertEquals(2, $result['results']['total_cross_refs']);

        // THREE-DOC-COURT (3 docs) should be first
        $this->assertEquals('THREE-DOC-COURT', $result['results']['cross_references'][0]['value']);
        $this->assertCount(3, $result['results']['cross_references'][0]['document_ids']);

        // TWO-DOC-REF (2 docs) should be second
        $this->assertEquals('TWO-DOC-REF', $result['results']['cross_references'][1]['value']);
        $this->assertCount(2, $result['results']['cross_references'][1]['document_ids']);
    }

    /**
     * @test
     */
    public function it_only_processes_completed_entity_analyses(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Completed analysis
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'COMPLETED-REF'],
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        // Pending analysis (should be ignored)
        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'COMPLETED-REF'],
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // No cross-references since only one completed analysis exists
        $this->assertEquals(0, $result['results']['total_cross_refs']);
    }

    /**
     * @test
     */
    public function it_tracks_total_mentions_across_documents(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Same entity mentioned multiple times in one document
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'MULTI-MENTION'],
                        ['case_number' => 'MULTI-MENTION'], // Mentioned twice in doc1
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'MULTI-MENTION'],
                    ],
                    'laws' => [],
                    'courts' => [],
                    'institutions' => [],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertEquals(1, $result['results']['total_cross_refs']);

        $crossRef = $result['results']['cross_references'][0];
        $this->assertCount(2, $crossRef['document_ids']); // 2 unique documents
        $this->assertEquals(3, $crossRef['total_mentions']); // 3 total mentions
    }

    /**
     * @test
     */
    public function it_includes_documents_analyzed_count_in_metadata(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => ['entities' => ['case_numbers' => [], 'laws' => [], 'courts' => [], 'institutions' => []]],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => ['entities' => ['case_numbers' => [], 'laws' => [], 'courts' => [], 'institutions' => []]],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('documents_analyzed', $result['metadata']);
        $this->assertEquals(2, $result['metadata']['documents_analyzed']);
    }

    /**
     * @test
     */
    public function it_handles_multiple_entity_types(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Both documents have shared entities across multiple types
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'SHARED-CASE'],
                    ],
                    'laws' => [
                        ['law' => 'SHARED-LAW'],
                    ],
                    'courts' => [
                        ['court' => 'SHARED-COURT'],
                    ],
                    'institutions' => [
                        ['institution' => 'SHARED-INST'],
                    ],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'entities' => [
                    'case_numbers' => [
                        ['case_number' => 'SHARED-CASE'],
                    ],
                    'laws' => [
                        ['law' => 'SHARED-LAW'],
                    ],
                    'courts' => [
                        ['court' => 'SHARED-COURT'],
                    ],
                    'institutions' => [
                        ['institution' => 'SHARED-INST'],
                    ],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // Should find 4 cross-references (one for each type)
        $this->assertEquals(4, $result['results']['total_cross_refs']);

        $types = array_column($result['results']['cross_references'], 'type');
        $this->assertContains('case_numbers', $types);
        $this->assertContains('laws', $types);
        $this->assertContains('courts', $types);
        $this->assertContains('institutions', $types);
    }
}
