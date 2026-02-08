<?php

namespace Tests\Unit\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\CaseFileRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseFileRegistryTest extends TestCase
{
    use UsesTestDatabase;

    private CaseFileRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CaseFileRegistry();
    }

    /**
     * @test
     */
    public function it_returns_empty_results_when_no_case_reference_analyses_exist(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->registry->build($case->id);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals(0, $result['results']['total_references']);
        $this->assertEquals(0, $result['results']['present']);
        $this->assertEquals(0, $result['results']['missing']);
    }

    /**
     * @test
     */
    public function it_marks_references_as_present_when_found_in_document_header(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Reference in header position (< 500 chars) = document's own reference
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'UP/I-034-04/2024-01/123', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        $this->assertEquals(1, $result['results']['total_references']);
        $this->assertEquals(1, $result['results']['present']);
        $this->assertEquals(0, $result['results']['missing']);

        // Verify present_references contains the reference
        $presentRefs = collect($result['results']['present_references']);
        $this->assertTrue($presentRefs->contains('reference_value', 'UP/I-034-04/2024-01/123'));
    }

    /**
     * @test
     */
    public function it_marks_references_as_missing_when_not_in_any_document_header(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Reference found far from header (position >= 500) = only referenced, not the doc's own reference
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
                    ['type' => 'case_number', 'value' => 'Pp Prz-74/2025', 'position' => 1500, 'mentions' => 2],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        $this->assertEquals(1, $result['results']['total_references']);
        $this->assertEquals(0, $result['results']['present']);
        $this->assertEquals(1, $result['results']['missing']);

        // Verify missing_references contains the reference
        $missingRefs = collect($result['results']['missing_references']);
        $this->assertTrue($missingRefs->contains('reference_value', 'Pp Prz-74/2025'));
    }

    /**
     * @test
     */
    public function it_tracks_total_mentions_across_documents(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Same reference mentioned in two documents
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
                    ['type' => 'case_number', 'value' => 'K-123/2025', 'position' => 100, 'mentions' => 3],
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
                    ['type' => 'case_number', 'value' => 'K-123/2025', 'position' => 200, 'mentions' => 2],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        // Should be present (both have header position)
        $this->assertEquals(1, $result['results']['present']);

        // Total mentions = 3 + 2 = 5
        $presentRef = $result['results']['present_references'][0];
        $this->assertEquals(5, $presentRef['total_mentions']);
        $this->assertCount(2, $presentRef['found_in_documents']);
    }

    /**
     * @test
     */
    public function it_tracks_klasa_urbroj_pairs(): void
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
                    ['type' => 'urbroj', 'value' => '514-05-01-01-24-1', 'position' => 80, 'mentions' => 1],
                ],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [
                    'UP/I-034-04/2024-01/123' => '514-05-01-01-24-1',
                ],
            ],
        ]);

        $result = $this->registry->build($case->id);

        $presentRefs = collect($result['results']['present_references']);

        $klasaRef = $presentRefs->firstWhere('reference_type', 'klasa');
        $this->assertEquals('514-05-01-01-24-1', $klasaRef['paired_urbroj']);

        $urbrojRef = $presentRefs->firstWhere('reference_type', 'urbroj');
        $this->assertEquals('UP/I-034-04/2024-01/123', $urbrojRef['paired_klasa']);
    }

    /**
     * @test
     */
    public function it_persists_registry_to_database_table(): void
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
                    ['type' => 'klasa', 'value' => 'PERSISTED-KLASA', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $this->registry->build($case->id);

        // Check database
        $this->assertDatabaseHas('case_reference_registry', [
            'case_id' => $case->id,
            'reference_type' => 'klasa',
            'reference_value' => 'PERSISTED-KLASA',
            'status' => 'present',
        ]);
    }

    /**
     * @test
     */
    public function it_clears_existing_registry_on_rebuild(): void
    {
        $case = LegalCase::factory()->create();
        $doc = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Insert old registry entry
        DB::table('case_reference_registry')->insert([
            'case_id' => $case->id,
            'reference_type' => 'klasa',
            'reference_value' => 'OLD-REFERENCE',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create new analysis
        DocumentAnalysis::create([
            'case_document_id' => $doc->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'NEW-REFERENCE', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $this->registry->build($case->id);

        // Old should be gone
        $this->assertDatabaseMissing('case_reference_registry', [
            'case_id' => $case->id,
            'reference_value' => 'OLD-REFERENCE',
        ]);

        // New should exist
        $this->assertDatabaseHas('case_reference_registry', [
            'case_id' => $case->id,
            'reference_value' => 'NEW-REFERENCE',
        ]);
    }

    /**
     * @test
     */
    public function it_returns_references_grouped_by_type(): void
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
                    ['type' => 'klasa', 'value' => 'KLASA-1', 'position' => 50, 'mentions' => 1],
                    ['type' => 'klasa', 'value' => 'KLASA-2', 'position' => 100, 'mentions' => 1],
                ],
                'urbroj' => [
                    ['type' => 'urbroj', 'value' => 'URBROJ-1', 'position' => 80, 'mentions' => 1],
                ],
                'broj' => [],
                'case_numbers' => [
                    ['type' => 'case_number', 'value' => 'K-123/2025', 'position' => 1500, 'mentions' => 1],
                ],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        $byType = $result['results']['references_by_type'];

        $this->assertArrayHasKey('klasa', $byType);
        $this->assertEquals(2, $byType['klasa']['total']);
        $this->assertEquals(2, $byType['klasa']['present']);

        $this->assertArrayHasKey('urbroj', $byType);
        $this->assertEquals(1, $byType['urbroj']['total']);

        $this->assertArrayHasKey('case_number', $byType);
        $this->assertEquals(1, $byType['case_number']['total']);
        $this->assertEquals(1, $byType['case_number']['missing']); // position > 500
    }

    /**
     * @test
     */
    public function it_only_processes_completed_case_reference_analyses(): void
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
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'COMPLETED-REF', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
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
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'PENDING-REF', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        // Only completed ref should be present
        $this->assertEquals(1, $result['results']['total_references']);
        $presentRefs = collect($result['results']['present_references']);
        $this->assertTrue($presentRefs->contains('reference_value', 'COMPLETED-REF'));
        $this->assertFalse($presentRefs->contains('reference_value', 'PENDING-REF'));
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
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('documents_analyzed', $result['metadata']);
        $this->assertEquals(1, $result['metadata']['documents_analyzed']);
    }

    /**
     * @test
     */
    public function it_handles_case_insensitive_reference_deduplication(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Same reference with different casing
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => 'case_references',
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'klasa' => [
                    ['type' => 'klasa', 'value' => 'UP/I-034-04/2024-01/123', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
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
                    ['type' => 'klasa', 'value' => 'up/i-034-04/2024-01/123', 'position' => 50, 'mentions' => 1],
                ],
                'urbroj' => [],
                'broj' => [],
                'case_numbers' => [],
                'klasa_urbroj_pairs' => [],
            ],
        ]);

        $result = $this->registry->build($case->id);

        // Should be deduplicated (case-insensitive)
        $this->assertEquals(1, $result['results']['total_references']);
        $this->assertEquals(2, $result['results']['present_references'][0]['total_mentions']);
    }
}
