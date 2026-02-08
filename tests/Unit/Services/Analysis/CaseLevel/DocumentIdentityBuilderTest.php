<?php

namespace Tests\Unit\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\DocumentIdentityBuilder;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentIdentityBuilderTest extends TestCase
{
    use UsesTestDatabase;

    private DocumentIdentityBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DocumentIdentityBuilder();
    }

    /**
     * @test
     */
    public function it_returns_empty_results_when_no_case_reference_analyses_exist(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->builder->build($case->id);

        $this->assertArrayHasKey('total_identities', $result);
        $this->assertArrayHasKey('present', $result);
        $this->assertArrayHasKey('missing', $result);
        $this->assertArrayHasKey('by_case_number', $result);
        $this->assertArrayHasKey('by_klasa', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result);

        $this->assertEquals(0, $result['total_identities']);
        $this->assertEquals(0, $result['present']);
        $this->assertEquals(0, $result['missing']);
    }

    /**
     * @test
     */
    public function it_identifies_document_own_identifiers_from_header_position(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Reference in header position (< 500) is document's own identifier
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'UP/I-034-04/2024-01/123', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [
                    ['type' => 'urbroj', 'value' => '511-01-02-03-24-1', 'position' => 100, 'mentions' => 1],
                ],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-123/2024', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [
                    'UP/I-034-04/2024-01/123' => '511-01-02-03-24-1',
                ],
            ],
        ]);

        $result = $this->builder->build($case->id);

        $this->assertEquals(1, $result['present']);
        $this->assertEquals(0, $result['missing']);

        // Should have one identity linking case number, klasa, urbroj
        $identities = $result['by_case_number'];
        $this->assertArrayHasKey('K-123/2024', $identities);
        $identity = $identities['K-123/2024'];
        $this->assertEquals($doc->id, $identity['document_id']);
        $this->assertEquals('present', $identity['status']);
        $this->assertEquals('UP/I-034-04/2024-01/123', $identity['klasa']);
        $this->assertEquals('511-01-02-03-24-1', $identity['urbroj']);
    }

    /**
     * @test
     */
    public function it_infers_missing_from_suffix_gaps(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Document with suffix -1
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'prekrsajni', 'value' => 'Pp Prz-74/2025-1', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        // Document with suffix -3 (gap at -2)
        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'prekrsajni', 'value' => 'Pp Prz-74/2025-3', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        // Should have 2 present and 1 missing (-2 inferred)
        $this->assertEquals(2, $result['present']);
        $this->assertEquals(1, $result['missing']);

        // The missing entry should be Pp Prz-74/2025-2
        $byCase = $result['by_case_number'];
        $this->assertArrayHasKey('Pp Prz-74/2025-2', $byCase);
        $this->assertEquals('missing', $byCase['Pp Prz-74/2025-2']['status']);
        $this->assertEquals('inferred_from_gap', $byCase['Pp Prz-74/2025-2']['inference_reason']);
    }

    /**
     * @test
     */
    public function it_infers_missing_from_references_not_present(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Document mentions another case number in body (not in header)
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'KLASA-OWN', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-100/2024', 'position' => 30, 'mentions' => 1],
                    // This one is referenced in body - not the doc's own
                    ['type' => 'case_number', 'sub_type' => 'prekrsajni', 'value' => 'Pp Prz-99/2024-5', 'position' => 1500, 'mentions' => 2],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        // Pp Prz-99/2024-5 is referenced but not present as any document's own
        $this->assertEquals(1, $result['present']); // K-100/2024
        $this->assertEquals(1, $result['missing']); // Pp Prz-99/2024-5

        $byCase = $result['by_case_number'];
        $this->assertArrayHasKey('Pp Prz-99/2024-5', $byCase);
        $this->assertEquals('missing', $byCase['Pp Prz-99/2024-5']['status']);
        $this->assertEquals('referenced_but_not_present', $byCase['Pp Prz-99/2024-5']['inference_reason']);
    }

    /**
     * @test
     */
    public function it_guesses_doc_type_from_suffix(): void
    {
        // Croatian court filings: suffix indicates order
        // -1 is usually initial filing, higher numbers are subsequent documents
        $this->assertEquals('initial_filing', $this->builder->guessDocTypeFromSuffix(1, 'Pp Prz'));
        $this->assertEquals('followup', $this->builder->guessDocTypeFromSuffix(2, 'Pp Prz'));
        $this->assertEquals('followup', $this->builder->guessDocTypeFromSuffix(5, 'K'));

        // Specific prefix heuristics
        $this->assertEquals('search_warrant', $this->builder->guessDocTypeFromSuffix(1, 'Pp Prz'));
        $this->assertEquals('detention_order', $this->builder->guessDocTypeFromSuffix(1, 'Kv'));
        $this->assertEquals('indictment', $this->builder->guessDocTypeFromSuffix(1, 'K'));
    }

    /**
     * @test
     */
    public function it_infers_institution_from_urbroj_code(): void
    {
        // URBROJ prefixes map to institutions
        $this->assertEquals('MUP (Ministarstvo unutarnjih poslova)', $this->builder->inferInstitution('511'));
        $this->assertEquals('Sud - Osijek', $this->builder->inferInstitution('2158'));
        $this->assertEquals('Sud - Zagreb', $this->builder->inferInstitution('2168'));
        $this->assertNull($this->builder->inferInstitution('9999'));
    }

    /**
     * @test
     */
    public function it_infers_role_from_case_prefix(): void
    {
        // Case prefix → procedural role
        $this->assertEquals('main_criminal', $this->builder->inferRole('K'));
        $this->assertEquals('search_warrant', $this->builder->inferRole('Pp Prz'));
        $this->assertEquals('detention', $this->builder->inferRole('Kv'));
        $this->assertEquals('appeal_criminal', $this->builder->inferRole('Kz'));
        $this->assertEquals('investigation', $this->builder->inferRole('KIR'));
        $this->assertNull($this->builder->inferRole('UNKNOWN'));
    }

    /**
     * @test
     */
    public function it_finds_document_date_from_date_context_extractor(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Case references analysis
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'value' => 'K-123/2024', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        // Date context analysis for same document
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'dates_with_context',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-05-15', 'position' => 40, 'event_type' => 'dostava'],
                    ['date' => '2024-05-20', 'position' => 500, 'event_type' => 'rociste'],
                ],
            ],
        ]);

        // Document date should be earliest date in header region
        $date = $this->builder->findDocumentDate($doc->id, $case->id);
        $this->assertEquals('2024-05-15', $date);
    }

    /**
     * @test
     */
    public function it_builds_case_number_matrix(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Two documents with same base case number but different suffixes
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'KLASA-A', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-100/2024-1', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'KLASA-B', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-100/2024-2', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        // Matrix should group related entries
        $byCase = $result['by_case_number'];
        $this->assertArrayHasKey('K-100/2024-1', $byCase);
        $this->assertArrayHasKey('K-100/2024-2', $byCase);

        // Verify structure
        $this->assertEquals($doc1->id, $byCase['K-100/2024-1']['document_id']);
        $this->assertEquals($doc2->id, $byCase['K-100/2024-2']['document_id']);
        $this->assertEquals('KLASA-A', $byCase['K-100/2024-1']['klasa']);
        $this->assertEquals('KLASA-B', $byCase['K-100/2024-2']['klasa']);
    }

    /**
     * @test
     */
    public function it_builds_klasa_matrix(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'UP/I-034-04/2024-01/123', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [
                    ['type' => 'urbroj', 'value' => '511-01-02-03-24-1', 'position' => 100, 'mentions' => 1],
                ],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-123/2024', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [
                    'UP/I-034-04/2024-01/123' => '511-01-02-03-24-1',
                ],
            ],
        ]);

        $result = $this->builder->build($case->id);

        $byKlasa = $result['by_klasa'];
        $this->assertArrayHasKey('UP/I-034-04/2024-01/123', $byKlasa);

        $entry = $byKlasa['UP/I-034-04/2024-01/123'];
        $this->assertEquals('511-01-02-03-24-1', $entry['urbroj']);
        $this->assertContains('K-123/2024', $entry['case_numbers']);
        $this->assertEquals($doc->id, $entry['document_ids'][0]);
    }

    /**
     * @test
     */
    public function it_handles_multiple_case_numbers_per_document(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Document with multiple case numbers in header
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'KLASA-SHARED', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-100/2024', 'position' => 30, 'mentions' => 1],
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'KIR-50/2024', 'position' => 80, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        // Both case numbers should be present and linked to same document
        $byCase = $result['by_case_number'];
        $this->assertArrayHasKey('K-100/2024', $byCase);
        $this->assertArrayHasKey('KIR-50/2024', $byCase);
        $this->assertEquals($doc->id, $byCase['K-100/2024']['document_id']);
        $this->assertEquals($doc->id, $byCase['KIR-50/2024']['document_id']);
    }

    /**
     * @test
     */
    public function it_extracts_suffix_from_case_number(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'kazneni', 'value' => 'K-123/2024-5', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        $identity = $result['by_case_number']['K-123/2024-5'];
        $this->assertEquals(5, $identity['suffix']);
        $this->assertEquals('K-123/2024', $identity['base_case_number']);
    }

    /**
     * @test
     */
    public function it_includes_institution_and_role_in_identity(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [
                    ['type' => 'urbroj', 'value' => '511-01-02-03-24-1', 'position' => 100, 'mentions' => 1],
                ],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'sub_type' => 'prekrsajni', 'value' => 'Pp Prz-74/2025-1', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        $identity = $result['by_case_number']['Pp Prz-74/2025-1'];
        $this->assertEquals('MUP (Ministarstvo unutarnjih poslova)', $identity['institution']);
        $this->assertEquals('search_warrant', $identity['role']);
    }

    /**
     * @test
     */
    public function it_only_processes_completed_analyses(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Completed
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'value' => 'K-COMPLETED/2024', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        // Pending - should be ignored
        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_PENDING,
            'results' => [
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'value' => 'K-PENDING/2024', 'position' => 30, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->builder->build($case->id);

        $this->assertEquals(1, $result['present']);
        $this->assertArrayHasKey('K-COMPLETED/2024', $result['by_case_number']);
        $this->assertArrayNotHasKey('K-PENDING/2024', $result['by_case_number']);
    }

    /**
     * @test
     */
    public function it_handles_complex_gap_detection_scenarios(): void
    {
        $case = LegalCase::factory()->create();

        // Create documents with suffixes 1, 2, 5, 6 - gaps at 3, 4
        foreach ([1, 2, 5, 6] as $suffix) {
            $doc = CaseDocument::factory()->create(['case_id' => $case->id]);
            DocumentAnalysis::create([
                'case_document_id' => $doc->id,
                'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
                'analysis_type' => 'case_references',
                'status' => DocumentAnalysis::STATUS_COMPLETED,
                'results' => [
                    'klasa' => [],
                    'urbroj' => [],
                    'broj' => [],
                    'case_numbers' => [
                        ['type' => 'case_number', 'value' => "K-100/2024-{$suffix}", 'position' => 30, 'mentions' => 1],
                    ],
                    'klasa_urbroj_pairs' => [],
                ],
            ]);
        }

        $result = $this->builder->build($case->id);

        // 4 present, 2 missing (3 and 4)
        $this->assertEquals(4, $result['present']);
        $this->assertEquals(2, $result['missing']);

        $byCase = $result['by_case_number'];
        $this->assertArrayHasKey('K-100/2024-3', $byCase);
        $this->assertArrayHasKey('K-100/2024-4', $byCase);
        $this->assertEquals('missing', $byCase['K-100/2024-3']['status']);
        $this->assertEquals('missing', $byCase['K-100/2024-4']['status']);
    }
}
