<?php

namespace Tests\Unit\Services\Analysis\CaseLevel;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use App\Services\Analysis\CaseLevel\DateClusterAnalyzer;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DateClusterAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    private DateClusterAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new DateClusterAnalyzer();
    }

    /**
     * @test
     */
    public function it_returns_empty_clusters_when_no_date_analyses_exist(): void
    {
        $case = LegalCase::factory()->create();

        $result = $this->analyzer->analyze($case->id);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEmpty($result['results']['clusters']);
        $this->assertEquals(0, $result['results']['total_dates']);
        $this->assertEquals(0, $result['results']['total_clusters']);
    }

    /**
     * @test
     */
    public function it_clusters_dates_within_three_days_of_each_other(): void
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Create date analysis with dates within 3 days
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-01-10', 'context' => 'Meeting held on January 10'],
                    ['date' => '2024-01-11', 'context' => 'Follow-up on January 11'],
                    ['date' => '2024-01-13', 'context' => 'Decision made January 13'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // All three dates are within 3 days of each other, so should form one cluster
        $this->assertEquals(1, $result['results']['total_clusters']);
        $this->assertEquals(3, $result['results']['total_dates']);

        $cluster = $result['results']['clusters'][0];
        $this->assertEquals('2024-01-10', $cluster['date_start']);
        $this->assertEquals('2024-01-13', $cluster['date_end']);
        $this->assertEquals(3, $cluster['date_count']);
    }

    /**
     * @test
     */
    public function it_creates_separate_clusters_for_dates_more_than_three_days_apart(): void
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Create date analysis with two distinct date ranges
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-01-01', 'context' => 'Event on Jan 1'],
                    ['date' => '2024-01-02', 'context' => 'Event on Jan 2'],
                    // Gap of more than 3 days
                    ['date' => '2024-01-15', 'context' => 'Event on Jan 15'],
                    ['date' => '2024-01-16', 'context' => 'Event on Jan 16'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertEquals(2, $result['results']['total_clusters']);
        $this->assertEquals(4, $result['results']['total_dates']);

        // First cluster: Jan 1-2
        $this->assertEquals('2024-01-01', $result['results']['clusters'][0]['date_start']);
        $this->assertEquals('2024-01-02', $result['results']['clusters'][0]['date_end']);

        // Second cluster: Jan 15-16
        $this->assertEquals('2024-01-15', $result['results']['clusters'][1]['date_start']);
        $this->assertEquals('2024-01-16', $result['results']['clusters'][1]['date_end']);
    }

    /**
     * @test
     */
    public function it_aggregates_dates_from_multiple_documents(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // First document with dates
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-02-10', 'context' => 'Doc1 event'],
                ],
            ],
        ]);

        // Second document with dates in same cluster range
        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-02-11', 'context' => 'Doc2 event'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertEquals(1, $result['results']['total_clusters']);
        $this->assertEquals(2, $result['results']['total_dates']);
        $this->assertEquals(1, $result['results']['multi_document_clusters']);

        $cluster = $result['results']['clusters'][0];
        $this->assertEquals(2, $cluster['document_count']);
        $this->assertCount(2, $cluster['document_ids']);
    }

    /**
     * @test
     */
    public function it_ranks_clusters_by_document_count(): void
    {
        $case = LegalCase::factory()->create();
        $doc1 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc2 = CaseDocument::factory()->create(['case_id' => $case->id]);
        $doc3 = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Single-document cluster (January) - put both dates in one analysis
        DocumentAnalysis::create([
            'case_document_id' => $doc1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-01-10', 'context' => 'January event'],
                    ['date' => '2024-03-15', 'context' => 'March event doc1'],
                ],
            ],
        ]);

        // Multi-document cluster (March - referenced by docs 2 and 3)
        DocumentAnalysis::create([
            'case_document_id' => $doc2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-03-16', 'context' => 'March event doc2'],
                ],
            ],
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $doc3->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-03-17', 'context' => 'March event doc3'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // Should have 2 clusters, with the 3-document cluster ranked first
        $this->assertEquals(2, $result['results']['total_clusters']);
        $this->assertEquals(3, $result['results']['clusters'][0]['document_count']);
        $this->assertEquals(1, $result['results']['clusters'][1]['document_count']);
    }

    /**
     * @test
     */
    public function it_only_processes_completed_date_analyses(): void
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Completed analysis
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => [
                'dates' => [
                    ['date' => '2024-05-01', 'context' => 'Completed analysis date'],
                ],
            ],
        ]);

        // Pending analysis (should be ignored) - use version 2
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 2,
            'results' => [
                'dates' => [
                    ['date' => '2024-05-02', 'context' => 'Pending analysis date'],
                ],
            ],
        ]);

        // Failed analysis (should be ignored) - use version 3
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_FAILED,
            'version' => 3,
            'results' => [
                'dates' => [
                    ['date' => '2024-05-03', 'context' => 'Failed analysis date'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        // Only one date from the completed analysis
        $this->assertEquals(1, $result['results']['total_dates']);
        $this->assertEquals('2024-05-01', $result['results']['clusters'][0]['date_start']);
    }

    /**
     * @test
     */
    public function it_includes_processing_metadata(): void
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => [
                'dates' => [
                    ['date' => '2024-06-01', 'context' => 'Test date'],
                ],
            ],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('processing_time_seconds', $result['metadata']);
        $this->assertArrayHasKey('documents_analyzed', $result['metadata']);
        $this->assertEquals(1, $result['metadata']['documents_analyzed']);
    }

    /**
     * @test
     */
    public function it_limits_context_snippets_to_five_per_cluster(): void
    {
        $case = LegalCase::factory()->create();
        $document = CaseDocument::factory()->create(['case_id' => $case->id]);

        // Create analysis with more than 5 dates in a cluster
        $dates = [];
        for ($i = 1; $i <= 8; $i++) {
            $dates[] = [
                'date' => '2024-07-0' . $i,
                'context' => "Context for day $i",
            ];
        }

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => ['dates' => $dates],
        ]);

        $result = $this->analyzer->analyze($case->id);

        $cluster = $result['results']['clusters'][0];
        $this->assertEquals(8, $cluster['date_count']);
        $this->assertCount(5, $cluster['contexts']); // Only 5 contexts returned
    }
}
