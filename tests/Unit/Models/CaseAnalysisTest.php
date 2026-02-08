<?php

namespace Tests\Unit\Models;

use App\Models\CaseAnalysis;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseAnalysisTest extends TestCase
{
    use UsesTestDatabase;

    public function test_case_analysis_can_be_created(): void
    {
        // Arrange & Act
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'timeline',
            'status' => CaseAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);

        // Assert
        $this->assertNotNull($analysis->id);
        $this->assertEquals('case-001', $analysis->case_id);
        $this->assertEquals('timeline', $analysis->analysis_type);
        $this->assertEquals('pending', $analysis->status);
    }

    public function test_case_analysis_casts_json_fields(): void
    {
        // Arrange
        $results = ['events' => [['date' => '2024-01-01', 'description' => 'Contract signed']]];
        $metadata = ['processing_time' => 2.5, 'model' => 'gpt-4'];
        $documentIds = ['doc-001', 'doc-002', 'doc-003'];

        // Act
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'timeline',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'results' => $results,
            'metadata' => $metadata,
            'document_ids' => $documentIds,
        ]);

        // Refresh from database
        $analysis->refresh();

        // Assert
        $this->assertIsArray($analysis->results);
        $this->assertIsArray($analysis->metadata);
        $this->assertIsArray($analysis->document_ids);
        $this->assertEquals($results, $analysis->results);
        $this->assertEquals($metadata, $analysis->metadata);
        $this->assertEquals($documentIds, $analysis->document_ids);
    }

    public function test_case_analysis_casts_datetime_fields(): void
    {
        // Arrange
        $now = now();

        // Act
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'summary',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'started_at' => $now,
            'completed_at' => $now->copy()->addMinutes(5),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $analysis->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $analysis->completed_at);
    }

    public function test_mark_processing_updates_status_and_started_at(): void
    {
        // Arrange
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'timeline',
            'status' => CaseAnalysis::STATUS_PENDING,
        ]);

        // Act
        $result = $analysis->markProcessing();

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(CaseAnalysis::STATUS_PROCESSING, $analysis->status);
        $this->assertNotNull($analysis->started_at);
    }

    public function test_mark_completed_updates_status_results_and_completed_at(): void
    {
        // Arrange
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'summary',
            'status' => CaseAnalysis::STATUS_PROCESSING,
        ]);

        $results = ['summary' => 'This case involves a contract dispute.'];
        $metadata = ['word_count' => 500];

        // Act
        $result = $analysis->markCompleted($results, $metadata);

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(CaseAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertEquals($results, $analysis->results);
        $this->assertEquals($metadata, $analysis->metadata);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_mark_failed_updates_status_error_and_completed_at(): void
    {
        // Arrange
        $analysis = CaseAnalysis::create([
            'case_id' => 'case-001',
            'analysis_type' => 'contradictions',
            'status' => CaseAnalysis::STATUS_PROCESSING,
        ]);

        // Act
        $result = $analysis->markFailed('API rate limit exceeded');

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(CaseAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertEquals('API rate limit exceeded', $analysis->error_message);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_constants_have_correct_values(): void
    {
        // Status constants
        $this->assertEquals('pending', CaseAnalysis::STATUS_PENDING);
        $this->assertEquals('processing', CaseAnalysis::STATUS_PROCESSING);
        $this->assertEquals('completed', CaseAnalysis::STATUS_COMPLETED);
        $this->assertEquals('failed', CaseAnalysis::STATUS_FAILED);
    }

    public function test_uses_correct_table_name(): void
    {
        // Arrange
        $analysis = new CaseAnalysis();

        // Assert
        $this->assertEquals('case_analyses', $analysis->getTable());
    }

    public function test_unique_constraint_on_case_id_type_version(): void
    {
        // Arrange - Create first analysis
        CaseAnalysis::create([
            'case_id' => 'case-unique-test',
            'analysis_type' => 'timeline',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'version' => 1,
        ]);

        // Act - Create same combination, should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        CaseAnalysis::create([
            'case_id' => 'case-unique-test',
            'analysis_type' => 'timeline',
            'status' => CaseAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);
    }

    public function test_can_create_new_version(): void
    {
        // Arrange - Create version 1
        $v1 = CaseAnalysis::create([
            'case_id' => 'case-version-test',
            'analysis_type' => 'summary',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['summary' => 'First version'],
        ]);

        // Act - Create version 2
        $v2 = CaseAnalysis::create([
            'case_id' => 'case-version-test',
            'analysis_type' => 'summary',
            'status' => CaseAnalysis::STATUS_COMPLETED,
            'version' => 2,
            'results' => ['summary' => 'Updated version'],
        ]);

        // Assert
        $this->assertEquals(1, $v1->version);
        $this->assertEquals(2, $v2->version);
        $this->assertCount(2, CaseAnalysis::where('case_id', 'case-version-test')->get());
    }
}
