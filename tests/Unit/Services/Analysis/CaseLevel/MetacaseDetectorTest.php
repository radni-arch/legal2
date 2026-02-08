<?php

namespace Tests\Unit\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\MetacaseDetector;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class MetacaseDetectorTest extends TestCase
{
    use UsesTestDatabase;

    private MetacaseDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new MetacaseDetector();
    }

    /**
     * @test
     */
    public function it_returns_empty_results_when_no_case_reference_analyses_exist(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->detector->detect($case->id);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEmpty($result['results']['hierarchy']);
        $this->assertEquals(0, $result['results']['satellite_count']);
    }

    /**
     * @test
     */
    public function it_identifies_k_prefix_as_main_criminal_case(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertCount(1, $result['results']['main_cases']);
        $this->assertEquals('K-123/2025', $result['results']['primary_main_case']);
        $mainCase = $result['results']['main_cases'][0];
        $this->assertEquals('K', $mainCase['prefix']);
        $this->assertEquals(1, $mainCase['priority']); // K is highest priority
    }

    /**
     * @test
     */
    public function it_identifies_pp_prz_as_search_warrant_satellite(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertEquals('K-123/2025', $result['results']['primary_main_case']);
        $this->assertEquals(1, $result['results']['satellite_count']);

        $satellite = $result['results']['hierarchy'][0];
        $this->assertEquals('Pp Prz-74/2025', $satellite['satellite_case']);
        $this->assertEquals('search_warrant', $satellite['relationship']);
    }

    /**
     * @test
     */
    public function it_identifies_kv_as_detention_hearing_satellite(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Kv-89/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertEquals(1, $result['results']['satellite_count']);

        $satellite = $result['results']['hierarchy'][0];
        $this->assertEquals('Kv-89/2025', $satellite['satellite_case']);
        $this->assertEquals('detention_hearing', $satellite['relationship']);
    }

    /**
     * @test
     */
    public function it_identifies_kz_as_appeal_satellite(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Kz-200/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertEquals(1, $result['results']['satellite_count']);

        $satellite = $result['results']['hierarchy'][0];
        $this->assertEquals('Kz-200/2025', $satellite['satellite_case']);
        $this->assertEquals('appeal', $satellite['relationship']);
    }

    /**
     * @test
     */
    public function it_calculates_higher_confidence_for_same_document_cooccurrence(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Both case numbers in the same document
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $satellite = $result['results']['hierarchy'][0];

        // Co-occurrence in same doc should give confidence >= 0.7 (0.5 + 0.2 for overlap)
        $this->assertGreaterThanOrEqual(0.7, $satellite['confidence']);
        // Check the doc ID is in co_occurring_documents (cast to string for comparison)
        $coOccurringIds = array_map('strval', $satellite['co_occurring_documents']);
        $this->assertContains((string) $doc->id, $coOccurringIds);
    }

    /**
     * @test
     */
    public function it_calculates_higher_confidence_for_same_year(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Different documents, but same year
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
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
                'klasa' => [],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'], // Same year
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $satellite = $result['results']['hierarchy'][0];

        // Same year should add 0.2 to confidence (base 0.3 for no overlap + 0.2 = 0.5)
        $this->assertGreaterThanOrEqual(0.5, $satellite['confidence']);
    }

    /**
     * @test
     */
    public function it_builds_multiple_satellite_relationships(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                    ['type' => 'case_number', 'value' => 'Kv-89/2025'],
                    ['type' => 'case_number', 'value' => 'Kz-200/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertEquals('K-123/2025', $result['results']['primary_main_case']);
        $this->assertEquals(3, $result['results']['satellite_count']);

        $relationships = array_column($result['results']['hierarchy'], 'relationship');
        $this->assertContains('search_warrant', $relationships);
        $this->assertContains('detention_hearing', $relationships);
        $this->assertContains('appeal', $relationships);
    }

    /**
     * @test
     */
    public function it_persists_hierarchy_to_database(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $this->detector->detect($case->id);

        $this->assertDatabaseHas('case_hierarchy', [
            'case_id' => $case->id,
            'main_case_number' => 'K-123/2025',
            'satellite_case_number' => 'Pp Prz-74/2025',
            'relationship' => 'search_warrant',
        ]);
    }

    /**
     * @test
     */
    public function it_clears_existing_hierarchy_on_rebuild(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Insert old hierarchy entry
        DB::table('case_hierarchy')->insert([
            'case_id' => $case->id,
            'main_case_number' => 'K-100/2024',
            'main_case_type' => 'K',
            'satellite_case_number' => 'Kv-100/2024',
            'satellite_case_type' => 'Kv',
            'relationship' => 'detention_hearing',
            'confidence' => 0.8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create new analysis with different case numbers
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
                    ['type' => 'case_number', 'value' => 'K-999/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-999/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $this->detector->detect($case->id);

        // Old should be gone
        $this->assertDatabaseMissing('case_hierarchy', [
            'case_id' => $case->id,
            'main_case_number' => 'K-100/2024',
        ]);

        // New should exist
        $this->assertDatabaseHas('case_hierarchy', [
            'case_id' => $case->id,
            'main_case_number' => 'K-999/2025',
            'satellite_case_number' => 'Pp Prz-999/2025',
        ]);
    }

    /**
     * @test
     */
    public function it_generates_human_readable_summary(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertArrayHasKey('summary', $result['results']);
        $this->assertStringContainsString('K-123/2025', $result['results']['summary']);
    }

    /**
     * @test
     */
    public function it_uses_most_mentioned_case_as_main_when_no_k_prefix(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc3 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // No K-prefix, so it should use the most mentioned
        foreach ([$doc1, $doc2, $doc3] as $i => $doc) {
            $caseNumbers = [['type' => 'case_number', 'value' => 'Pp Prz-74/2025']];
            if ($i < 2) {
                // Only 2 docs have the other case
                $caseNumbers[] = ['type' => 'case_number', 'value' => 'Kv-89/2025'];
            }

            DocumentAnalysis::create([
                'case_document_id' => $doc->id,
                'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
                'analysis_type' => 'case_references',
                'status' => DocumentAnalysis::STATUS_COMPLETED,
                'results' => [
                    'klasa' => [],
                    'urbroj' => [],
                    'broj' => [],
                    'case_numbers' => $caseNumbers,
                    'klasa_urbroj_pairs' => [],
                ],
            ]);
        }

        $result = $this->detector->detect($case->id);

        // Pp Prz-74/2025 appears in 3 docs, Kv-89/2025 in 2 docs
        // So Pp Prz should be chosen as main (most mentioned)
        $this->assertEquals('Pp Prz-74/2025', $result['results']['primary_main_case']);
    }

    /**
     * @test
     */
    public function it_sorts_hierarchy_by_confidence_descending(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Main + satellite 1 in doc1 (co-occur = higher confidence)
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        // Satellite 2 in doc2 (no co-occurrence = lower confidence)
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
                    ['type' => 'case_number', 'value' => 'Kv-89/2024'], // Different year too
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertEquals(2, $result['results']['satellite_count']);

        // Pp Prz should be first (higher confidence due to co-occurrence + same year)
        $this->assertEquals('Pp Prz-74/2025', $result['results']['hierarchy'][0]['satellite_case']);
        $this->assertGreaterThan(
            $result['results']['hierarchy'][1]['confidence'],
            $result['results']['hierarchy'][0]['confidence']
        );
    }

    /**
     * @test
     */
    public function it_only_processes_completed_analyses(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Completed analysis
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        // Pending analysis (should be ignored)
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
                    ['type' => 'case_number', 'value' => 'Pp Prz-IGNORED/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        // Only the completed analysis should be processed
        $this->assertEquals('K-123/2025', $result['results']['primary_main_case']);
        $this->assertEquals(0, $result['results']['satellite_count']); // No satellites from completed
    }

    /**
     * @test
     */
    public function it_includes_case_types_found_in_results(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025'],
                    ['type' => 'case_number', 'value' => 'Kv-89/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertContains('K', $result['results']['case_types_found']);
        $this->assertContains('Pp Prz', $result['results']['case_types_found']);
        $this->assertContains('Kv', $result['results']['case_types_found']);
    }

    /**
     * @test
     */
    public function it_includes_processing_metadata(): void
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
                    ['type' => 'case_number', 'value' => 'K-123/2025'],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->detector->detect($case->id);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('documents_analyzed', $result['metadata']);
        $this->assertArrayHasKey('total_case_numbers_found', $result['metadata']);
        $this->assertEquals(1, $result['metadata']['documents_analyzed']);
        $this->assertEquals(1, $result['metadata']['total_case_numbers_found']);
    }
}
