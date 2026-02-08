<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TextractManager;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for TextractManager pipeline health metrics.
 *
 * Task 3.4: Health Dashboard Metrics
 * Tests the getPipelineHealthProperty() computed property which provides
 * embedding status breakdown, graph sync breakdown, average pipeline
 * duration, failed job tracking, and stale pending detection.
 */
class PipelineHealthMetricsTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected LegalCase $legalCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->legalCase = LegalCase::factory()->create([
            'case_number' => 'HEALTH-001',
            'title' => 'Health Metrics Test Case',
        ]);

        Storage::fake('local');
        Storage::fake('s3');

        Storage::disk('local')->makeDirectory('textract/source');
        Storage::disk('local')->makeDirectory('textract/json');
        Storage::disk('local')->makeDirectory('textract/output');

        // Clear any cached stats
        Cache::forget('textract_manager_stats');
        Cache::forget('textract_pipeline_health');
    }

    // -------------------------------------------------------
    // Test 1: Embedding status breakdown counts by status
    // -------------------------------------------------------

    public function test_embedding_breakdown_counts_by_status(): void
    {
        // Create jobs with various embedding statuses
        TextractJob::factory()->count(3)->create(['embedding_status' => 'pending']);
        TextractJob::factory()->count(2)->create(['embedding_status' => 'synced']);
        TextractJob::factory()->count(1)->create(['embedding_status' => 'failed']);

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('embedding_breakdown', $health);

        $breakdown = $health['embedding_breakdown'];
        $this->assertEquals(3, $breakdown['pending']);
        $this->assertEquals(2, $breakdown['synced']);
        $this->assertEquals(1, $breakdown['failed']);
    }

    // -------------------------------------------------------
    // Test 2: Graph sync breakdown counts by status
    // -------------------------------------------------------

    public function test_graph_sync_breakdown_counts_by_status(): void
    {
        TextractJob::factory()->count(4)->create(['graph_sync_status' => 'pending']);
        TextractJob::factory()->count(1)->create(['graph_sync_status' => 'synced']);
        TextractJob::factory()->count(2)->create(['graph_sync_status' => 'failed']);

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('graph_sync_breakdown', $health);

        $breakdown = $health['graph_sync_breakdown'];
        $this->assertEquals(4, $breakdown['pending']);
        $this->assertEquals(1, $breakdown['synced']);
        $this->assertEquals(2, $breakdown['failed']);
    }

    // -------------------------------------------------------
    // Test 3: Average pipeline duration calculated correctly
    // -------------------------------------------------------

    public function test_average_pipeline_duration_calculated_correctly(): void
    {
        // Create succeeded jobs with known created_at and updated_at
        // to produce a predictable average duration.
        // Job 1: 60 seconds duration
        TextractJob::factory()->create([
            'status' => 'succeeded',
            'created_at' => now()->subSeconds(120),
            'updated_at' => now()->subSeconds(60),
        ]);

        // Job 2: 120 seconds duration
        TextractJob::factory()->create([
            'status' => 'succeeded',
            'created_at' => now()->subSeconds(180),
            'updated_at' => now()->subSeconds(60),
        ]);

        // Job that is NOT succeeded should be excluded from average
        TextractJob::factory()->create([
            'status' => 'queued',
            'created_at' => now()->subSeconds(9999),
            'updated_at' => now(),
        ]);

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertArrayHasKey('avg_pipeline_duration_seconds', $health);

        // Average of 60 and 120 = 90 seconds
        $avgDuration = $health['avg_pipeline_duration_seconds'];
        $this->assertIsFloat($avgDuration);
        // Allow a 2-second margin for test execution time
        $this->assertEqualsWithDelta(90.0, $avgDuration, 2.0);
    }

    // -------------------------------------------------------
    // Test 4: Failed jobs returned in reverse chronological order
    // -------------------------------------------------------

    public function test_failed_jobs_returned_in_reverse_chronological_order(): void
    {
        // Create failed jobs with staggered updated_at timestamps
        $oldest = TextractJob::factory()->create([
            'status' => 'failed',
            'drive_file_name' => 'oldest-failed.pdf',
            'error' => 'Timeout error',
            'updated_at' => now()->subHours(3),
        ]);

        $middle = TextractJob::factory()->create([
            'status' => 'failed',
            'drive_file_name' => 'middle-failed.pdf',
            'error' => 'S3 error',
            'updated_at' => now()->subHours(2),
        ]);

        $newest = TextractJob::factory()->create([
            'status' => 'failed',
            'drive_file_name' => 'newest-failed.pdf',
            'error' => 'Parse error',
            'updated_at' => now()->subHour(),
        ]);

        // Succeeded job should NOT appear in failed list
        TextractJob::factory()->create([
            'status' => 'succeeded',
            'drive_file_name' => 'success-doc.pdf',
        ]);

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertArrayHasKey('failed_jobs', $health);

        $failedJobs = $health['failed_jobs'];
        $this->assertCount(3, $failedJobs);

        // Verify reverse chronological order (newest first)
        $this->assertEquals('newest-failed.pdf', $failedJobs[0]['drive_file_name']);
        $this->assertEquals('middle-failed.pdf', $failedJobs[1]['drive_file_name']);
        $this->assertEquals('oldest-failed.pdf', $failedJobs[2]['drive_file_name']);

        // Verify each entry has expected fields
        $this->assertArrayHasKey('id', $failedJobs[0]);
        $this->assertArrayHasKey('drive_file_name', $failedJobs[0]);
        $this->assertArrayHasKey('error', $failedJobs[0]);
        $this->assertArrayHasKey('updated_at', $failedJobs[0]);
    }

    // -------------------------------------------------------
    // Test 5: Stale pending detection finds old pending jobs
    // -------------------------------------------------------

    public function test_stale_pending_detection_finds_old_pending_jobs(): void
    {
        // Stale pending: pending status, created > 1 hour ago
        TextractJob::factory()->count(2)->create([
            'status' => 'pending',
            'created_at' => now()->subHours(2),
        ]);

        // Fresh pending: pending status, created < 1 hour ago (not stale)
        TextractJob::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subMinutes(30),
        ]);

        // Old but NOT pending (succeeded) -- should not count
        TextractJob::factory()->create([
            'status' => 'succeeded',
            'created_at' => now()->subHours(3),
        ]);

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertArrayHasKey('stale_pending_count', $health);
        $this->assertEquals(2, $health['stale_pending_count']);
    }

    // -------------------------------------------------------
    // Test 6: Empty database returns sensible defaults
    // -------------------------------------------------------

    public function test_pipeline_health_with_no_jobs_returns_defaults(): void
    {
        // No jobs in DB at all
        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $this->assertIsArray($health);
        $this->assertArrayHasKey('embedding_breakdown', $health);
        $this->assertArrayHasKey('graph_sync_breakdown', $health);
        $this->assertArrayHasKey('avg_pipeline_duration_seconds', $health);
        $this->assertArrayHasKey('failed_jobs', $health);
        $this->assertArrayHasKey('stale_pending_count', $health);

        // Defaults
        $this->assertEmpty($health['embedding_breakdown']);
        $this->assertEmpty($health['graph_sync_breakdown']);
        $this->assertEquals(0.0, $health['avg_pipeline_duration_seconds']);
        $this->assertCount(0, $health['failed_jobs']);
        $this->assertEquals(0, $health['stale_pending_count']);
    }

    // -------------------------------------------------------
    // Test 7: Failed jobs limited to 10 most recent
    // -------------------------------------------------------

    public function test_failed_jobs_limited_to_ten(): void
    {
        // Create 15 failed jobs
        for ($i = 1; $i <= 15; $i++) {
            TextractJob::factory()->create([
                'status' => 'failed',
                'drive_file_name' => "failed-job-{$i}.pdf",
                'error' => "Error {$i}",
                'updated_at' => now()->subMinutes(16 - $i), // newest = i=15
            ]);
        }

        $component = Livewire::test(TextractManager::class);
        $health = $component->get('pipelineHealth');

        $failedJobs = $health['failed_jobs'];
        $this->assertCount(10, $failedJobs);

        // Most recent should be first
        $this->assertEquals('failed-job-15.pdf', $failedJobs[0]['drive_file_name']);
    }
}
