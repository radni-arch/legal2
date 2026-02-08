<?php

namespace Tests\Unit\Services;

use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use App\Services\TextractHealthMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextractHealthMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TextractHealthMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TextractHealthMetricsService();
    }

    public function test_get_health_metrics_returns_expected_structure(): void
    {
        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('embedding_status_breakdown', $metrics);
        $this->assertArrayHasKey('graph_sync_status_breakdown', $metrics);
        $this->assertArrayHasKey('pipeline_durations', $metrics);
        $this->assertArrayHasKey('failed_job_ages', $metrics);
        $this->assertArrayHasKey('stale_pending_jobs', $metrics);
        $this->assertArrayHasKey('data_consistency', $metrics);
    }

    public function test_get_embedding_status_breakdown_groups_by_status(): void
    {
        // Arrange
        TextractJob::factory()->count(3)->create(['embedding_status' => 'pending']);
        TextractJob::factory()->count(2)->create(['embedding_status' => 'synced']);
        TextractJob::factory()->count(1)->create(['embedding_status' => 'failed']);

        // Act
        $metrics = $this->service->getHealthMetrics();
        $breakdown = $metrics['embedding_status_breakdown'];

        // Assert
        $this->assertEquals(3, $breakdown['pending']);
        $this->assertEquals(2, $breakdown['synced']);
        $this->assertEquals(1, $breakdown['failed']);
    }

    public function test_get_graph_sync_status_breakdown_groups_by_status(): void
    {
        // Arrange
        TextractJob::factory()->count(4)->create(['graph_sync_status' => 'pending']);
        TextractJob::factory()->count(2)->create(['graph_sync_status' => 'synced']);
        TextractJob::factory()->count(1)->create(['graph_sync_status' => 'failed']);
        TextractJob::factory()->count(1)->create(['graph_sync_status' => 'blocked']);

        // Act
        $metrics = $this->service->getHealthMetrics();
        $breakdown = $metrics['graph_sync_status_breakdown'];

        // Assert
        $this->assertEquals(4, $breakdown['pending']);
        $this->assertEquals(2, $breakdown['synced']);
        $this->assertEquals(1, $breakdown['failed']);
        $this->assertEquals(1, $breakdown['blocked']);
    }

    public function test_get_pipeline_durations_calculates_average_time(): void
    {
        // Arrange - Create jobs with known time differences
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Job 1: 30 minutes from creation to sync
        $job1 = TextractJob::factory()->create([
            'embedding_status' => 'synced',
            'created_at' => Carbon::parse('2025-01-15 11:00:00'),
            'updated_at' => Carbon::parse('2025-01-15 11:30:00'),
        ]);

        // Job 2: 60 minutes from creation to sync
        $job2 = TextractJob::factory()->create([
            'embedding_status' => 'synced',
            'created_at' => Carbon::parse('2025-01-15 10:00:00'),
            'updated_at' => Carbon::parse('2025-01-15 11:00:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();
        $avgMinutes = $metrics['pipeline_durations']['avg_ocr_to_embedding_minutes'];

        // Assert - Average should be 45 minutes ((30+60)/2)
        $this->assertEqualsWithDelta(45.0, $avgMinutes, 0.5);

        Carbon::setTestNow(); // Reset
    }

    public function test_get_pipeline_durations_returns_zero_when_no_synced_jobs(): void
    {
        // Arrange - No synced jobs
        TextractJob::factory()->count(3)->create(['embedding_status' => 'pending']);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert
        $this->assertEquals(0, $metrics['pipeline_durations']['avg_ocr_to_embedding_minutes']);
    }

    public function test_get_failed_job_ages_returns_oldest_failures(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Failed 6 hours ago
        TextractJob::factory()->create([
            'status' => 'failed',
            'updated_at' => Carbon::parse('2025-01-15 06:00:00'),
        ]);

        // Failed 2 hours ago
        TextractJob::factory()->create([
            'embedding_status' => 'failed',
            'updated_at' => Carbon::parse('2025-01-15 10:00:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();
        $failedAges = $metrics['failed_job_ages'];

        // Assert
        $this->assertEqualsWithDelta(6.0, $failedAges['oldest_failed_hours'], 0.1);
        $this->assertCount(2, $failedAges['failed_jobs']);

        Carbon::setTestNow();
    }

    public function test_get_failed_job_ages_includes_graph_sync_failures(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        TextractJob::factory()->create([
            'status' => 'completed',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'failed',
            'updated_at' => Carbon::parse('2025-01-15 08:00:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();
        $failedAges = $metrics['failed_job_ages'];

        // Assert - 4 hours old
        $this->assertEqualsWithDelta(4.0, $failedAges['oldest_failed_hours'], 0.1);
        $this->assertCount(1, $failedAges['failed_jobs']);

        Carbon::setTestNow();
    }

    public function test_get_failed_job_ages_limits_to_10_jobs(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Create 15 failed jobs
        TextractJob::factory()->count(15)->create([
            'status' => 'failed',
            'updated_at' => Carbon::parse('2025-01-15 10:00:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert
        $this->assertCount(10, $metrics['failed_job_ages']['failed_jobs']);

        Carbon::setTestNow();
    }

    public function test_get_stale_pending_jobs_detects_stuck_embedding_pending(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Stale: pending for 2 hours
        TextractJob::factory()->count(2)->create([
            'embedding_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 09:00:00'),
        ]);

        // Fresh: pending for 30 minutes
        TextractJob::factory()->create([
            'embedding_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 11:30:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert - Only 2 are stale (> 1 hour old)
        $this->assertEquals(2, $metrics['stale_pending_jobs']['stale_embedding_pending']);

        Carbon::setTestNow();
    }

    public function test_get_stale_pending_jobs_detects_stuck_graph_sync_pending(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Stale: pending for 3 hours
        TextractJob::factory()->count(3)->create([
            'graph_sync_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 08:00:00'),
        ]);

        // Fresh: pending for 45 minutes
        TextractJob::factory()->count(2)->create([
            'graph_sync_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 11:15:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert - Only 3 are stale (> 1 hour old)
        $this->assertEquals(3, $metrics['stale_pending_jobs']['stale_graph_sync_pending']);

        Carbon::setTestNow();
    }

    public function test_get_stale_pending_jobs_uses_one_hour_threshold(): void
    {
        // Arrange
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

        // Exactly 1 hour old - should NOT be stale (need to be > 1 hour)
        TextractJob::factory()->create([
            'embedding_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 11:00:00'),
        ]);

        // 61 minutes old - should be stale
        TextractJob::factory()->create([
            'embedding_status' => 'pending',
            'updated_at' => Carbon::parse('2025-01-15 10:59:00'),
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert - Only the 61-minute old job is stale
        $this->assertEquals(1, $metrics['stale_pending_jobs']['stale_embedding_pending']);

        Carbon::setTestNow();
    }

    public function test_get_data_consistency_detects_missing_textract_documents(): void
    {
        // Arrange
        // Job with synced embeddings but NO documents (inconsistent)
        TextractJob::factory()->create([
            'embedding_status' => 'synced',
        ]);

        // Job with synced embeddings AND documents (consistent)
        $consistentJob = TextractJob::factory()->create([
            'embedding_status' => 'synced',
        ]);
        TextractDocument::factory()->create([
            'textract_job_id' => $consistentJob->id,
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert - Only 1 job has missing documents
        $this->assertEquals(1, $metrics['data_consistency']['missing_textract_documents']);
    }

    public function test_get_data_consistency_excludes_non_synced_jobs(): void
    {
        // Arrange
        // Pending job without documents - this is expected, not an inconsistency
        TextractJob::factory()->create([
            'embedding_status' => 'pending',
        ]);

        // Failed job without documents - also not an inconsistency
        TextractJob::factory()->create([
            'embedding_status' => 'failed',
        ]);

        // Act
        $metrics = $this->service->getHealthMetrics();

        // Assert - No data inconsistencies
        $this->assertEquals(0, $metrics['data_consistency']['missing_textract_documents']);
    }

    public function test_handles_empty_database_gracefully(): void
    {
        // Act - No data in database
        $metrics = $this->service->getHealthMetrics();

        // Assert - Should return empty/zero values without errors
        $this->assertEmpty($metrics['embedding_status_breakdown']);
        $this->assertEmpty($metrics['graph_sync_status_breakdown']);
        $this->assertEquals(0, $metrics['pipeline_durations']['avg_ocr_to_embedding_minutes']);
        $this->assertEquals(0, $metrics['failed_job_ages']['oldest_failed_hours']);
        $this->assertEmpty($metrics['failed_job_ages']['failed_jobs']);
        $this->assertEquals(0, $metrics['stale_pending_jobs']['stale_embedding_pending']);
        $this->assertEquals(0, $metrics['stale_pending_jobs']['stale_graph_sync_pending']);
        $this->assertEquals(0, $metrics['data_consistency']['missing_textract_documents']);
    }
}
