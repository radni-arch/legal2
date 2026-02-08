<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Components\TextractJobActions;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\ProcessTextractJob;
use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TextractJobActionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_shows_retry_ocr_for_failed_jobs(): void
    {
        $job = TextractJob::factory()->failed()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('retryOcr', $actionKeys);
    }

    /** @test */
    public function it_shows_re_extract_for_completed_jobs(): void
    {
        $job = TextractJob::factory()->completed()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('reExtract', $actionKeys);
    }

    /** @test */
    public function it_shows_generate_embeddings_for_completed_jobs(): void
    {
        $job = TextractJob::factory()->completed()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('generateEmbeddings', $actionKeys);
    }

    /** @test */
    public function it_shows_sync_to_graph_when_embeddings_synced(): void
    {
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('syncToGraph', $actionKeys);
    }

    /** @test */
    public function it_does_not_show_sync_to_graph_when_embeddings_pending(): void
    {
        $job = TextractJob::factory()->completed()->create(['embedding_status' => 'pending']);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertNotContains('syncToGraph', $actionKeys);
    }

    /** @test */
    public function it_shows_retry_embeddings_for_failed_embeddings(): void
    {
        $job = TextractJob::factory()->completed()->create(['embedding_status' => 'failed']);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('retryEmbeddings', $actionKeys);
    }

    /** @test */
    public function it_shows_retry_graph_sync_for_failed_graph(): void
    {
        $job = TextractJob::factory()->completed()->create(['graph_sync_status' => 'failed']);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('retryGraphSync', $actionKeys);
    }

    /** @test */
    public function it_shows_view_and_edit_content_for_completed_jobs(): void
    {
        $job = TextractJob::factory()->completed()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('viewContent', $actionKeys);
        $this->assertContains('editContent', $actionKeys);
    }

    /** @test */
    public function it_shows_view_in_graph_when_graph_synced(): void
    {
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create([
            'graph_sync_status' => 'synced',
            'graph_synced_at' => now(),
        ]);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('viewInGraph', $actionKeys);
    }

    /** @test */
    public function it_shows_no_view_actions_for_queued_jobs(): void
    {
        $job = TextractJob::factory()->queued()->create();

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertNotContains('viewContent', $actionKeys);
        $this->assertNotContains('editContent', $actionKeys);
    }

    /** @test */
    public function retry_ocr_dispatches_job_and_resets_status(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->failed()->create();

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('retryOcr');

        $job->refresh();
        $this->assertEquals('queued', $job->status);
        $this->assertNull($job->error);

        Queue::assertPushed(ProcessTextractJob::class);
    }

    /** @test */
    public function re_extract_resets_all_statuses(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create([
            'graph_sync_status' => 'synced',
        ]);

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('reExtract');

        $job->refresh();
        $this->assertEquals('queued', $job->status);
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertEquals('pending', $job->graph_sync_status);

        Queue::assertPushed(ProcessTextractJob::class);
    }

    /** @test */
    public function generate_embeddings_dispatches_job(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->create();

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('generateEmbeddings');

        $job->refresh();
        $this->assertEquals('pending', $job->embedding_status);

        // ADR-001: Use RegenerateTextractEmbeddings as canonical embedding job
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id;
        });

        // ADR-001: GenerateEmbeddingsJob (System C) must NOT be dispatched
        Queue::assertNotPushed(GenerateEmbeddingsJob::class);
    }

    /** @test */
    public function retry_embeddings_dispatches_job(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->create([
            'embedding_status' => 'failed',
            'error' => 'Previous error',
        ]);

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('retryEmbeddings');

        $job->refresh();
        $this->assertEquals('pending', $job->embedding_status);
        $this->assertNull($job->error);

        // ADR-001: Use RegenerateTextractEmbeddings as canonical embedding job
        Queue::assertPushed(RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id;
        });

        // ADR-001: GenerateEmbeddingsJob (System C) must NOT be dispatched
        Queue::assertNotPushed(GenerateEmbeddingsJob::class);
    }

    /** @test */
    public function sync_to_graph_dispatches_job(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->embeddingsSynced()->create();

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('syncToGraph');

        $job->refresh();
        $this->assertEquals('pending', $job->graph_sync_status);

        Queue::assertPushed(SyncTextractToGraph::class);
    }

    /** @test */
    public function it_shows_mark_as_reviewed_for_jobs_needing_review(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence'],
                'ocrQuality' => ['confidence' => 0.70],
            ],
        ]);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertContains('markAsReviewed', $actionKeys);
    }

    /** @test */
    public function it_does_not_show_mark_as_reviewed_when_not_needed(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'metadata' => [
                'needsReview' => false,
                'ocrQuality' => ['confidence' => 0.95],
            ],
        ]);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);
        $actions = $component->get('availableActions');

        $actionKeys = array_column($actions, 'key');
        $this->assertNotContains('markAsReviewed', $actionKeys);
    }

    /** @test */
    public function mark_as_reviewed_clears_needs_review_flag(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->create([
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence'],
                'ocrQuality' => ['confidence' => 0.70],
            ],
        ]);

        $this->assertTrue($job->needsReview());

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('markAsReviewed');

        $job->refresh();
        $this->assertFalse($job->needsReview());
        $this->assertArrayNotHasKey('needsReview', $job->metadata);
        $this->assertArrayNotHasKey('reviewReasons', $job->metadata);
        // ocrQuality should be preserved
        $this->assertArrayHasKey('ocrQuality', $job->metadata);
    }

    /** @test */
    public function mark_as_reviewed_dispatches_embedding_job_with_skip_option(): void
    {
        Queue::fake();
        $job = TextractJob::factory()->completed()->create([
            'extracted_content' => 'Some content',
            'embedding_status' => 'pending',
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence'],
            ],
        ]);

        Livewire::test(TextractJobActions::class, ['job' => $job])
            ->call('markAsReviewed');

        Queue::assertPushed(\App\Jobs\RegenerateTextractEmbeddings::class, function ($queuedJob) use ($job) {
            return $queuedJob->textractJobId === $job->id
                && ($queuedJob->options['skipReviewCheck'] ?? false) === true;
        });
    }

    /** @test */
    public function it_shows_needs_review_badge_in_actions(): void
    {
        $job = TextractJob::factory()->completed()->create([
            'metadata' => [
                'needsReview' => true,
                'reviewReasons' => ['Low overall confidence'],
            ],
        ]);

        $component = Livewire::test(TextractJobActions::class, ['job' => $job]);

        $this->assertTrue($component->get('job')->needsReview());
    }
}
