<?php

namespace Tests\Feature\Livewire;

use App\Actions\Textract\ProcessDrivePdf;
use App\Http\Livewire\TextractManager;
use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for TextractManager Livewire component
 *
 * Covers all manager functions including job processing, status updates,
 * content editing, embedding regeneration, graph sync, filtering, pagination,
 * search, deletion, error handling, and permission checks.
 */
class TextractManagerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected LegalCase $legalCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a legal case for job assignment
        $this->legalCase = LegalCase::factory()->create([
            'case_number' => 'TEST-001',
            'title' => 'Test Legal Case',
        ]);

        // Mock storage disks
        Storage::fake('local');
        Storage::fake('s3');

        // Create textract directories
        Storage::disk('local')->makeDirectory('textract/source');
        Storage::disk('local')->makeDirectory('textract/json');
        Storage::disk('local')->makeDirectory('textract/output');
    }

    /**
     * Test 1: Component renders job list correctly
     */
    public function test_component_renders_job_list(): void
    {
        // Create test jobs
        $job1 = TextractJob::factory()->create([
            'drive_file_name' => 'test-document-1.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
        ]);

        $job2 = TextractJob::factory()->create([
            'drive_file_name' => 'test-document-2.pdf',
            'status' => 'queued',
            'case_id' => $this->legalCase->id,
        ]);

        Livewire::test(TextractManager::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.textract-manager')
            ->assertSet('search', '')
            ->assertSet('statusFilter', 'all')
            ->assertSet('perPage', 20)
            ->assertSet('autoRefresh', false)
            ->assertSee($job1->drive_file_name)
            ->assertSee($job2->drive_file_name);
    }

    /**
     * Test 2: Start new processing triggers job
     */
    public function test_start_new_processing_triggers_job(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'test-drive-file-123',
            'drive_file_name' => 'test-document.pdf',
            'status' => 'queued',
            'case_id' => $this->legalCase->id,
        ]);

        Livewire::test(TextractManager::class)
            ->call('processJob', $job->id, false)
            ->assertDispatched('success');

        Queue::assertPushed(ProcessDrivePdf::class);
    }

    /**
     * Test 3: Job status updates are reflected in computed properties
     */
    public function test_job_status_updates_in_computed_properties(): void
    {
        // Create jobs with different statuses
        TextractJob::factory()->create(['status' => 'queued']);
        TextractJob::factory()->create(['status' => 'started']);
        TextractJob::factory()->create(['status' => 'analyzing']);
        TextractJob::factory()->create(['status' => 'succeeded']);
        TextractJob::factory()->create(['status' => 'failed']);

        $component = Livewire::test(TextractManager::class);

        // Access computed stats property
        $stats = $component->get('stats');

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(1, $stats['queued']);
        $this->assertEquals(2, $stats['processing']); // started + analyzing
        $this->assertEquals(1, $stats['succeeded']);
        $this->assertEquals(1, $stats['failed']);
    }

    /**
     * Test 4: Retry failed job works
     */
    public function test_retry_failed_job_works(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_id' => 'test-drive-file-456',
            'drive_file_name' => 'failed-document.pdf',
            'status' => 'failed',
            'error' => 'Network timeout',
            'case_id' => $this->legalCase->id,
        ]);

        Livewire::test(TextractManager::class)
            ->call('retryJob', $job->id)
            ->assertDispatched('success');

        // Verify job status was reset
        $job->refresh();
        $this->assertEquals('queued', $job->status);
        $this->assertNull($job->error);

        Queue::assertPushed(ProcessDrivePdf::class);
    }

    /**
     * Test 5: Cancel running job (not implemented - skipped)
     */
    public function test_cancel_running_job(): void
    {
        $this->markTestSkipped('Cancel running job functionality is not yet implemented in the component');
    }

    /**
     * Test 6: View job results displays correctly
     */
    public function test_view_job_results_displays_correctly(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_id' => 'test-drive-file-789',
            'drive_file_name' => 'completed-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'This is the extracted content from the PDF document.',
            'metadata' => [
                'pages' => 10,
                'ocrQuality' => ['confidence' => 0.95],
            ],
        ]);

        // Create mock files
        Storage::disk('local')->put(
            'textract/json/'.$job->drive_file_id.'.json',
            json_encode(['pages' => 10])
        );

        Livewire::test(TextractManager::class)
            ->call('viewJobDetails', $job->id)
            ->assertSet('selectedJobId', $job->id)
            ->assertSet('selectedJobData.drive_file_name', $job->drive_file_name)
            ->assertSet('selectedJobData.status', 'succeeded')
            ->assertSet('selectedJobData.has_textract_json', true);
    }

    /**
     * Test 7: Edit content triggers modal
     */
    public function test_edit_content_triggers_modal(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'editable-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original extracted content',
            'manual_content' => null,
        ]);

        Livewire::test(TextractManager::class)
            ->call('editContent', $job->id)
            ->assertSet('showContentEditModal', true)
            ->assertSet('editingContent.id', $job->id)
            ->assertSet('editingContent.drive_file_name', $job->drive_file_name)
            ->assertSet('editingContent.extracted_content', 'Original extracted content')
            ->assertSet('editingContent.manual_content', 'Original extracted content');
    }

    /**
     * Test 8: Save edited content works
     */
    public function test_save_edited_content_works(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'editable-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original content',
            'manual_content' => null,
            'manually_edited' => false,
        ]);

        $component = Livewire::test(TextractManager::class)
            ->call('editContent', $job->id)
            ->assertSet('showContentEditModal', true);

        // Modify the content
        $component->set('editingContent.manual_content', 'Manually edited content with corrections')
            ->call('saveContent')
            ->assertDispatched('success')
            ->assertSet('showContentEditModal', false);

        // Verify database was updated
        $job->refresh();
        $this->assertEquals('Manually edited content with corrections', $job->manual_content);
        $this->assertTrue($job->manually_edited);
        $this->assertEquals($this->user->id, $job->edited_by);
        $this->assertNotNull($job->content_edited_at);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test 9: Regenerate embeddings triggers
     */
    public function test_regenerate_embeddings_triggers(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'document-with-content.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Content for embedding generation',
            'embedding_status' => 'synced',
        ]);

        Livewire::test(TextractManager::class)
            ->call('regenerateEmbeddings', $job->id)
            ->assertDispatched('success');

        // Verify job queue
        Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class);

        // Verify status was updated
        $job->refresh();
        $this->assertEquals('pending', $job->embedding_status);
    }

    /**
     * Test 10: Sync to graph triggers
     */
    public function test_sync_to_graph_triggers(): void
    {
        Queue::fake();

        // Enable Neo4j sync
        config(['neo4j.sync.enabled' => true]);

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'document-for-graph.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Content for graph sync',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
        ]);

        Livewire::test(TextractManager::class)
            ->call('syncToGraph', $job->id)
            ->assertDispatched('success');

        // Verify job queue
        Queue::assertPushed(\App\Jobs\SyncTextractToGraph::class);

        // Verify status was updated
        $job->refresh();
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test 11: Batch operations work (not implemented - skipped)
     */
    public function test_batch_operations_work(): void
    {
        $this->markTestSkipped('Batch operations are not yet implemented in the component');
    }

    /**
     * Test 12: Filters by status
     */
    public function test_filters_by_status(): void
    {
        // Create jobs with different statuses
        $succeededJob = TextractJob::factory()->create([
            'drive_file_name' => 'succeeded-doc.pdf',
            'status' => 'succeeded',
        ]);

        $failedJob = TextractJob::factory()->create([
            'drive_file_name' => 'failed-doc.pdf',
            'status' => 'failed',
        ]);

        $queuedJob = TextractJob::factory()->create([
            'drive_file_name' => 'queued-doc.pdf',
            'status' => 'queued',
        ]);

        // Test "all" filter (default)
        $component = Livewire::test(TextractManager::class)
            ->assertSet('statusFilter', 'all')
            ->assertSee('succeeded-doc.pdf')
            ->assertSee('failed-doc.pdf')
            ->assertSee('queued-doc.pdf');

        // Test "succeeded" filter
        $component->set('statusFilter', 'succeeded')
            ->assertSee('succeeded-doc.pdf')
            ->assertDontSee('failed-doc.pdf')
            ->assertDontSee('queued-doc.pdf');

        // Test "failed" filter
        $component->set('statusFilter', 'failed')
            ->assertSee('failed-doc.pdf')
            ->assertDontSee('succeeded-doc.pdf')
            ->assertDontSee('queued-doc.pdf');
    }

    /**
     * Test 13: Pagination works
     */
    public function test_pagination_works(): void
    {
        // Create more jobs than perPage limit
        for ($i = 1; $i <= 25; $i++) {
            TextractJob::factory()->create([
                'drive_file_name' => "document-{$i}.pdf",
                'status' => 'succeeded',
            ]);
        }

        $component = Livewire::test(TextractManager::class)
            ->assertSet('perPage', 20);

        // Get first page jobs
        $jobs = $component->get('jobs');
        $this->assertCount(20, $jobs);

        // Test pagination navigation
        $component->call('nextPage')
            ->assertSee('document-21.pdf');
    }

    /**
     * Test 14: Search by case_id and file name
     */
    public function test_search_by_case_id_and_file_name(): void
    {
        $job1 = TextractJob::factory()->create([
            'drive_file_id' => 'ABC123',
            'drive_file_name' => 'contract-agreement.pdf',
            'status' => 'succeeded',
        ]);

        $job2 = TextractJob::factory()->create([
            'drive_file_id' => 'XYZ789',
            'drive_file_name' => 'invoice-2024.pdf',
            'status' => 'succeeded',
        ]);

        // Test search by file name
        Livewire::test(TextractManager::class)
            ->set('search', 'contract')
            ->assertSee('contract-agreement.pdf')
            ->assertDontSee('invoice-2024.pdf');

        // Test search by drive file ID
        Livewire::test(TextractManager::class)
            ->set('search', 'XYZ789')
            ->assertSee('invoice-2024.pdf')
            ->assertDontSee('contract-agreement.pdf');

        // Test empty search shows all
        Livewire::test(TextractManager::class)
            ->set('search', '')
            ->assertSee('contract-agreement.pdf')
            ->assertSee('invoice-2024.pdf');
    }

    /**
     * Test 15: Export job list (not implemented - skipped)
     */
    public function test_export_job_list(): void
    {
        $this->markTestSkipped('Export job list functionality is not yet implemented in the component');
    }

    /**
     * Test 16: Delete job confirmation
     */
    public function test_delete_job_confirmation(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'document-to-delete.pdf',
            'status' => 'failed',
        ]);

        $jobId = $job->id;

        Livewire::test(TextractManager::class)
            ->call('deleteJob', $jobId)
            ->assertDispatched('success');

        // Verify job was deleted
        $this->assertNull(TextractJob::find($jobId));
    }

    /**
     * Test 17: Error handling displays correctly
     */
    public function test_error_handling_displays(): void
    {
        // Test error when processing job without case assignment
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'document-no-case.pdf',
            'status' => 'queued',
            'case_id' => null,
        ]);

        Livewire::test(TextractManager::class)
            ->call('processJob', $job->id)
            ->assertDispatched('error', message: 'Please select a case for this job before processing.');

        // Test error when regenerating embeddings with no content
        $jobNoContent = TextractJob::factory()->create([
            'drive_file_name' => 'document-no-content.pdf',
            'status' => 'succeeded',
            'extracted_content' => null,
        ]);

        Livewire::test(TextractManager::class)
            ->call('regenerateEmbeddings', $jobNoContent->id)
            ->assertDispatched('error', message: 'No content available for embedding generation');

        // Test error when syncing to graph without embeddings
        config(['neo4j.sync.enabled' => true]);

        $jobNoEmbeddings = TextractJob::factory()->create([
            'drive_file_name' => 'document-no-embeddings.pdf',
            'status' => 'succeeded',
            'extracted_content' => 'Some content',
            'embedding_status' => 'pending',
        ]);

        Livewire::test(TextractManager::class)
            ->call('syncToGraph', $jobNoEmbeddings->id)
            ->assertDispatched('error', message: 'Embeddings must be synced before graph sync. Please regenerate embeddings first.');
    }

    /**
     * Test 18: Permission checks and access control
     */
    public function test_permission_checks_and_access_control(): void
    {
        // Test authenticated user can access component
        $component = Livewire::actingAs($this->user)
            ->test(TextractManager::class)
            ->assertStatus(200);

        // Test that job is properly associated with user when editing
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'document-for-editing.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original content',
        ]);

        $component->call('editContent', $job->id)
            ->set('editingContent.manual_content', 'Edited by authenticated user')
            ->call('saveContent')
            ->assertDispatched('success');

        // Verify the authenticated user is recorded as editor
        $job->refresh();
        $this->assertEquals($this->user->id, $job->edited_by);
        $this->assertEquals('Edited by authenticated user', $job->manual_content);
    }

    /**
     * Test 19: Manual processing with validation
     */
    public function test_manual_processing_with_validation(): void
    {
        Queue::fake();

        // Test validation error when fields are missing
        Livewire::test(TextractManager::class)
            ->set('manualDriveFileId', '')
            ->set('manualDriveFileName', '')
            ->call('processManual')
            ->assertDispatched('error', message: 'Both Drive File ID and Name are required');

        // Test validation error when case is not selected
        Livewire::test(TextractManager::class)
            ->set('manualDriveFileId', 'MANUAL123')
            ->set('manualDriveFileName', 'manual-upload.pdf')
            ->set('selectedCaseForManual', null)
            ->call('processManual')
            ->assertDispatched('error', message: 'Please select a case for manual processing.');

        // Test successful manual processing
        Livewire::test(TextractManager::class)
            ->set('manualDriveFileId', 'MANUAL456')
            ->set('manualDriveFileName', 'manual-document.pdf')
            ->set('selectedCaseForManual', (string) $this->legalCase->id)
            ->set('forceTextractForManual', false)
            ->call('processManual')
            ->assertDispatched('success')
            ->assertSet('manualDriveFileId', '')
            ->assertSet('manualDriveFileName', '')
            ->assertSet('selectedCaseForManual', null)
            ->assertSet('forceTextractForManual', false);

        Queue::assertPushed(ProcessDrivePdf::class);

        // Verify job was created
        $job = TextractJob::where('drive_file_id', 'MANUAL456')->first();
        $this->assertNotNull($job);
        $this->assertEquals('manual-document.pdf', $job->drive_file_name);
        $this->assertEquals($this->legalCase->id, $job->case_id);
    }

    /**
     * Test 20: Content view modal displays all metadata
     */
    public function test_content_view_modal_displays_all_metadata(): void
    {
        $editor = User::factory()->create(['name' => 'Jane Editor']);

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'detailed-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original extracted content',
            'manual_content' => 'Manually edited content',
            'manually_edited' => true,
            'edited_by' => $editor->id,
            'content_edited_at' => now(),
            'embedding_status' => 'synced',
            'graph_sync_status' => 'synced',
            'embedding_synced_at' => now(),
            'graph_synced_at' => now(),
            'metadata' => [
                'pages' => 15,
                'ocrQuality' => ['confidence' => 0.92],
            ],
        ]);

        Livewire::test(TextractManager::class)
            ->call('viewContent', $job->id)
            ->assertSet('showContentViewModal', true)
            ->assertSet('viewingContent.id', $job->id)
            ->assertSet('viewingContent.drive_file_name', 'detailed-document.pdf')
            ->assertSet('viewingContent.extracted_content', 'Original extracted content')
            ->assertSet('viewingContent.manual_content', 'Manually edited content')
            ->assertSet('viewingContent.effective_content', 'Manually edited content')
            ->assertSet('viewingContent.manually_edited', true)
            ->assertSet('viewingContent.edited_by_name', 'Jane Editor')
            ->assertSet('viewingContent.embedding_status', 'synced')
            ->assertSet('viewingContent.graph_sync_status', 'synced')
            ->assertSet('viewingContent.case_label', $this->legalCase->title);
    }

    /**
     * Test 21: Reset to original content
     */
    public function test_reset_to_original_content(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'reset-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original extracted content',
            'manual_content' => 'Manually edited content',
            'manually_edited' => true,
            'edited_by' => $this->user->id,
            'content_edited_at' => now(),
        ]);

        Livewire::test(TextractManager::class)
            ->call('resetToOriginal', $job->id)
            ->assertDispatched('success');

        // Verify content was reset
        $job->refresh();
        $this->assertNull($job->manual_content);
        $this->assertFalse($job->manually_edited);
        $this->assertNull($job->content_edited_at);
        $this->assertNull($job->edited_by);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);
    }

    /**
     * Test 22: Assign case to job
     */
    public function test_assign_case_to_job(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'unassigned-document.pdf',
            'status' => 'queued',
            'case_id' => null,
        ]);

        $newCase = LegalCase::factory()->create([
            'case_number' => 'NEW-CASE-001',
            'title' => 'New Legal Case',
        ]);

        Livewire::test(TextractManager::class)
            ->set("selectedCaseForJob.{$job->id}", (string) $newCase->id)
            ->call('assignJobCase', $job->id)
            ->assertDispatched('success');

        // Verify case was assigned
        $job->refresh();
        $this->assertEquals($newCase->id, $job->case_id);
    }

    /**
     * Test 23: Reprocess job with force Textract
     */
    public function test_reprocess_job_with_force_textract(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'reprocess-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low OCR confidence'],
                'ocrQuality' => ['confidence' => 0.65],
            ],
        ]);

        Livewire::test(TextractManager::class)
            ->call('reprocessJob', $job->id)
            ->assertDispatched('success');

        // Verify job was reset for reprocessing
        $job->refresh();
        $this->assertEquals('queued', $job->status);
        $this->assertNull($job->error);
        $this->assertArrayNotHasKey('ocrQuality', $job->metadata);
        $this->assertArrayNotHasKey('needsReview', $job->metadata);

        Queue::assertPushed(ProcessDrivePdf::class);
    }

    /**
     * Test 24: Empty content save validation
     */
    public function test_empty_content_save_validation(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'empty-content-doc.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original content',
        ]);

        $component = Livewire::test(TextractManager::class)
            ->call('editContent', $job->id)
            ->assertSet('showContentEditModal', true);

        // Try to save empty content
        $component->set('editingContent.manual_content', '   ')
            ->call('saveContent')
            ->assertDispatched('error', message: 'Content cannot be empty')
            ->assertSet('showContentEditModal', true);

        // Verify database was not updated
        $job->refresh();
        $this->assertNull($job->manual_content);
        $this->assertFalse($job->manually_edited);
    }

    /**
     * Test 25: No changes detected when saving identical content
     */
    public function test_no_changes_detected_when_saving_identical_content(): void
    {
        $job = TextractJob::factory()->create([
            'drive_file_name' => 'identical-content-doc.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Original content',
            'manual_content' => 'Edited content',
            'manually_edited' => true,
        ]);

        $component = Livewire::test(TextractManager::class)
            ->call('editContent', $job->id)
            ->assertSet('showContentEditModal', true);

        // Save without making any changes
        $component->call('saveContent')
            ->assertDispatched('info', message: 'No changes detected')
            ->assertSet('showContentEditModal', false);
    }

    /**
     * Test 26: approveAndEmbed clears needsReview flag, sets reviewApprovedAt, dispatches embedding job
     *
     * Task 2.4: Dead needsReview Flag Cleanup - Wire needsReview to block auto-embedding
     * until human review. The approveAndEmbed action allows a user to approve a flagged job
     * and trigger embedding generation.
     */
    public function test_approve_and_embed_clears_flag_and_dispatches_job(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'needs-review-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Content flagged for review',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence: 70.00% (threshold: 82.00%)'],
                'ocrQuality' => ['confidence' => 0.70, 'coverage' => 0.80],
            ],
        ]);

        Livewire::test(TextractManager::class)
            ->call('approveAndEmbed', $job->id)
            ->assertDispatched('notify', message: 'Review approved, embedding queued');

        // Verify the needsReview flag was cleared
        $job->refresh();
        $this->assertFalse($job->metadata['needsReview']);

        // Verify reviewApprovedAt was set in metadata
        $this->assertArrayHasKey('reviewApprovedAt', $job->metadata);
        $this->assertNotEmpty($job->metadata['reviewApprovedAt']);

        // Verify ocrQuality metadata is preserved (not wiped)
        $this->assertArrayHasKey('ocrQuality', $job->metadata);

        // Verify embedding job was dispatched
        Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class, function ($embeddingJob) use ($job) {
            return $embeddingJob->textractJobId === $job->id;
        });
    }

    /**
     * Test 27: approveAndEmbed fails gracefully for non-existent job
     *
     * Task 2.4: Ensure approveAndEmbed handles invalid job IDs gracefully.
     */
    public function test_approve_and_embed_fails_for_nonexistent_job(): void
    {
        Livewire::test(TextractManager::class)
            ->call('approveAndEmbed', 99999)
            ->assertDispatched('error');
    }

    /**
     * Test 28: approveAndEmbed works on job that does not have needsReview set
     *
     * Task 2.4: Even if needsReview is not set, the method should still work
     * (idempotent behavior - useful for re-triggering embedding manually).
     */
    public function test_approve_and_embed_works_on_job_without_needs_review(): void
    {
        Queue::fake();

        $job = TextractJob::factory()->create([
            'drive_file_name' => 'no-review-flag-document.pdf',
            'status' => 'succeeded',
            'case_id' => $this->legalCase->id,
            'extracted_content' => 'Normal content without review flag',
            'metadata' => [
                'ocrQuality' => ['confidence' => 0.95, 'coverage' => 0.90],
            ],
        ]);

        Livewire::test(TextractManager::class)
            ->call('approveAndEmbed', $job->id)
            ->assertDispatched('notify', message: 'Review approved, embedding queued');

        $job->refresh();
        $this->assertFalse($job->metadata['needsReview']);
        $this->assertArrayHasKey('reviewApprovedAt', $job->metadata);

        Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class);
    }
}
