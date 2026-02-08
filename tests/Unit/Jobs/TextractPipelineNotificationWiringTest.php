<?php

namespace Tests\Unit\Jobs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Jobs\ProcessTextractJob;
use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use App\Models\User;
use App\Notifications\TextractPipelineNotification;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use App\Services\TextractVectorStoreService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Weak Sector 1: Wire TextractPipelineNotification to job lifecycle events
 *
 * Tests that notifications are dispatched from:
 * - ProcessTextractJob (on completion and failure)
 * - RegenerateTextractEmbeddings (on success and failure)
 * - SyncTextractToGraph (on success and failure)
 */
class TextractPipelineNotificationWiringTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;
    protected TextractJob $textractJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->textractJob = TextractJob::factory()->create([
            'drive_file_id' => 'test-file-id',
            'drive_file_name' => 'test-document.pdf',
            'status' => 'pending',
        ]);

        // Default: disable auto-sync and follow-up jobs to isolate test behavior
        Config::set('textract.auto_sync', false);
        Config::set('distributed-processing.textract.auto_extract_tables', false);
        Config::set('distributed-processing.textract.auto_generate_embeddings', false);
        Config::set('neo4j.sync.enabled', false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Helper to bind ProcessDrivePdf stub
    private function bindProcessDrivePdfStub(callable $handler): void
    {
        $this->app->instance(ProcessDrivePdf::class, new class($handler)
        {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(string $driveFileId, string $driveFileName, bool $forceTextract = false): void
            {
                ($this->handler)($driveFileId, $driveFileName, $forceTextract);
            }
        });
    }

    //--------------------------------------------------------------------------
    // ProcessTextractJob Notification Tests
    //--------------------------------------------------------------------------

    /** @test */
    public function process_textract_job_dispatches_notification_on_success_when_enabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        $this->bindProcessDrivePdfStub(function () {
            $this->textractJob->update([
                'status' => 'succeeded',
                'extracted_content' => 'Extracted text content',
            ]);
        });

        $queueJob = new ProcessTextractJob($this->textractJob->id, null, $this->user->id);
        $queueJob->handle();

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'completed'
                    && $notification->job->id === $this->textractJob->id;
            }
        );
    }

    /** @test */
    public function process_textract_job_does_not_dispatch_notification_when_config_disabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', false);

        $this->bindProcessDrivePdfStub(function () {
            $this->textractJob->update([
                'status' => 'succeeded',
                'extracted_content' => 'Extracted text content',
            ]);
        });

        $queueJob = new ProcessTextractJob($this->textractJob->id, null, $this->user->id);
        $queueJob->handle();

        Notification::assertNothingSent();
    }

    /** @test */
    public function process_textract_job_dispatches_notification_on_permanent_failure(): void
    {
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        Log::shouldReceive('error')->andReturn(null);

        $exception = new \Exception('Permanent failure after all retries');

        $queueJob = new ProcessTextractJob($this->textractJob->id, null, $this->user->id);
        $queueJob->failed($exception);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) use ($exception) {
                return $notification->event === 'failed'
                    && str_contains($notification->message, 'Permanent failure');
            }
        );
    }

    /** @test */
    public function process_textract_job_does_not_notify_without_user_id(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        $this->bindProcessDrivePdfStub(function () {
            $this->textractJob->update([
                'status' => 'succeeded',
                'extracted_content' => 'Extracted text content',
            ]);
        });

        // Create job WITHOUT user ID
        $queueJob = new ProcessTextractJob($this->textractJob->id, null, null);
        $queueJob->handle();

        Notification::assertNothingSent();
    }

    //--------------------------------------------------------------------------
    // RegenerateTextractEmbeddings Notification Tests
    //--------------------------------------------------------------------------

    /** @test */
    public function regenerate_embeddings_job_dispatches_notification_on_success_when_enabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        // Prepare job for embedding
        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content for embedding',
            'embedding_status' => 'pending',
        ]);

        // Mock TextractVectorStoreService
        $vectorStore = Mockery::mock(TextractVectorStoreService::class);
        $vectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($this->textractJob->id, [])
            ->andReturn(['chunks' => 5, 'status' => 'success']);

        $queueJob = new RegenerateTextractEmbeddings($this->textractJob->id, [], $this->user->id);
        $queueJob->handle($vectorStore);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'embedding_complete'
                    && $notification->job->id === $this->textractJob->id;
            }
        );
    }

    /** @test */
    public function regenerate_embeddings_job_does_not_dispatch_notification_when_config_disabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', false);

        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content for embedding',
            'embedding_status' => 'pending',
        ]);

        $vectorStore = Mockery::mock(TextractVectorStoreService::class);
        $vectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->andReturn(['chunks' => 5, 'status' => 'success']);

        $queueJob = new RegenerateTextractEmbeddings($this->textractJob->id, [], $this->user->id);
        $queueJob->handle($vectorStore);

        Notification::assertNothingSent();
    }

    /** @test */
    public function regenerate_embeddings_job_dispatches_notification_on_permanent_failure(): void
    {
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content',
            'embedding_status' => 'processing',
        ]);

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);

        $exception = new \Exception('Embedding API permanently failed');

        $queueJob = new RegenerateTextractEmbeddings($this->textractJob->id, [], $this->user->id);
        $queueJob->failed($exception);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'failed'
                    && str_contains($notification->message, 'Embedding');
            }
        );
    }

    //--------------------------------------------------------------------------
    // SyncTextractToGraph Notification Tests
    //--------------------------------------------------------------------------

    /** @test */
    public function sync_to_graph_job_dispatches_notification_on_success_when_enabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', true);
        Config::set('neo4j.sync.enabled', true);

        // Prepare job for graph sync
        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        // Mock GraphDatabaseService to indicate Neo4j is available
        $graphDb = Mockery::mock(GraphDatabaseService::class);
        $graphDb->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(GraphDatabaseService::class, $graphDb);

        // Mock GraphRagOrchestrator
        $graphRag = Mockery::mock(GraphRagOrchestrator::class);
        $graphRag->shouldReceive('syncTextractJob')
            ->once()
            ->with($this->textractJob->id);

        $queueJob = new SyncTextractToGraph($this->textractJob->id, $this->user->id);
        $queueJob->handle($graphRag);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'graph_synced'
                    && $notification->job->id === $this->textractJob->id;
            }
        );
    }

    /** @test */
    public function sync_to_graph_job_does_not_dispatch_notification_when_config_disabled(): void
    {
        Queue::fake();
        Notification::fake();
        Config::set('textract.notifications.enabled', false);
        Config::set('neo4j.sync.enabled', true);

        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'pending',
        ]);

        $graphDb = Mockery::mock(GraphDatabaseService::class);
        $graphDb->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(GraphDatabaseService::class, $graphDb);

        $graphRag = Mockery::mock(GraphRagOrchestrator::class);
        $graphRag->shouldReceive('syncTextractJob')->once();

        $queueJob = new SyncTextractToGraph($this->textractJob->id, $this->user->id);
        $queueJob->handle($graphRag);

        Notification::assertNothingSent();
    }

    /** @test */
    public function sync_to_graph_job_dispatches_notification_on_permanent_failure(): void
    {
        Notification::fake();
        Config::set('textract.notifications.enabled', true);

        $this->textractJob->update([
            'status' => 'completed',
            'extracted_content' => 'Content',
            'embedding_status' => 'synced',
            'graph_sync_status' => 'processing',
        ]);

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);

        $exception = new \Exception('Neo4j connection permanently failed');

        $queueJob = new SyncTextractToGraph($this->textractJob->id, $this->user->id);
        $queueJob->failed($exception);

        Notification::assertSentTo(
            $this->user,
            TextractPipelineNotification::class,
            function ($notification) {
                return $notification->event === 'failed'
                    && str_contains($notification->message, 'Graph sync');
            }
        );
    }

    //--------------------------------------------------------------------------
    // Config Key Declaration Test
    //--------------------------------------------------------------------------

    /** @test */
    public function notifications_enabled_config_key_declared_in_textract_config(): void
    {
        // Read the raw config array
        $textractConfig = require base_path('config/textract.php');

        $this->assertArrayHasKey('notifications', $textractConfig);
        $this->assertArrayHasKey('enabled', $textractConfig['notifications']);
        $this->assertFalse($textractConfig['notifications']['enabled']);
    }
}
