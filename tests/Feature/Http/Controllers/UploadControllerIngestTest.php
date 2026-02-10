<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\Ingest\ProcessIngestRunJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature test: Upload controller triggers ingest pipeline
 *
 * SOT-001: Verifies that both direct and chunked/complete uploads
 * trigger the ingest orchestrator and return ingest_run_id in response.
 */
class UploadControllerIngestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        // Bypass policy checks (role column may not exist in fresh migration)
        Gate::before(fn () => true);

        $this->user = User::factory()->create([
            'api_token' => Str::random(60),
        ]);
    }

    /**
     * Helper: make an authenticated API request.
     */
    protected function apiPost(string $uri, array $data = [], array $headers = [])
    {
        $headers['Authorization'] = 'Bearer ' . $this->user->api_token;

        return $this->postJson($uri, $data, $headers);
    }

    /** @test */
    public function direct_upload_triggers_ingest_and_returns_ingest_run_id(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->apiPost('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => ['path', 'url', 'size', 'mime', 'name', 'ingest_run_id'],
        ]);

        // Verify IngestRun was created
        $ingestRunId = $response->json('data.ingest_run_id');
        $this->assertNotNull($ingestRunId);
        $this->assertDatabaseHas('ingest_runs', [
            'id' => $ingestRunId,
            'source' => 'uploader',
            'status' => 'pending',
            'user_id' => $this->user->id,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(ProcessIngestRunJob::class);
    }

    /** @test */
    public function direct_upload_passes_case_id_to_ingest(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->apiPost('/api/uploads', [
            'file' => $file,
            'case_id' => 'case-xyz-789',
        ]);

        $response->assertStatus(200);

        $ingestRunId = $response->json('data.ingest_run_id');
        $this->assertDatabaseHas('ingest_runs', [
            'id' => $ingestRunId,
            'case_id' => 'case-xyz-789',
        ]);
    }

    /** @test */
    public function chunked_upload_complete_triggers_ingest(): void
    {
        Queue::fake();

        // Step 1: Start a chunked upload
        $startResponse = $this->apiPost('/api/uploads/start', [
            'filename' => 'large-doc.pdf',
            'totalSize' => 100,
            'chunkSize' => 100,
            'mime' => 'application/pdf',
        ]);

        $startResponse->assertStatus(200);
        $uploadId = $startResponse->json('data.id');

        // Step 2: Upload a single chunk (totalSize == chunkSize, so 1 chunk)
        $chunk = UploadedFile::fake()->create('chunk', 100);
        $this->apiPost("/api/uploads/{$uploadId}/chunk/0", [
            'chunk' => $chunk,
        ]);

        // Step 3: Complete the upload
        $completeResponse = $this->apiPost("/api/uploads/{$uploadId}/complete");

        $completeResponse->assertStatus(200);
        $completeResponse->assertJsonPath('data.status', 'completed');
        $completeResponse->assertJsonStructure([
            'data' => ['status', 'id', 'filename', 'path', 'url', 'ingest_run_id'],
        ]);

        $ingestRunId = $completeResponse->json('data.ingest_run_id');
        $this->assertNotNull($ingestRunId);

        $this->assertDatabaseHas('ingest_runs', [
            'id' => $ingestRunId,
            'source' => 'uploader',
            'original_filename' => 'large-doc.pdf',
            'user_id' => $this->user->id,
        ]);

        Queue::assertPushed(ProcessIngestRunJob::class);
    }

    /** @test */
    public function chunked_upload_complete_passes_case_id(): void
    {
        Queue::fake();

        $startResponse = $this->apiPost('/api/uploads/start', [
            'filename' => 'large-doc.pdf',
            'totalSize' => 100,
            'chunkSize' => 100,
            'mime' => 'application/pdf',
        ]);

        $uploadId = $startResponse->json('data.id');

        $chunk = UploadedFile::fake()->create('chunk', 100);
        $this->apiPost("/api/uploads/{$uploadId}/chunk/0", [
            'chunk' => $chunk,
        ]);

        $completeResponse = $this->apiPost("/api/uploads/{$uploadId}/complete", [
            'case_id' => 'case-abc-123',
        ]);

        $completeResponse->assertStatus(200);
        $ingestRunId = $completeResponse->json('data.ingest_run_id');

        $this->assertDatabaseHas('ingest_runs', [
            'id' => $ingestRunId,
            'case_id' => 'case-abc-123',
        ]);
    }

    /** @test */
    public function incomplete_chunked_upload_does_not_trigger_ingest(): void
    {
        Queue::fake();

        // Start a chunked upload that expects 2 chunks
        $startResponse = $this->apiPost('/api/uploads/start', [
            'filename' => 'large-doc.pdf',
            'totalSize' => 200,
            'chunkSize' => 100,
            'mime' => 'application/pdf',
        ]);

        $uploadId = $startResponse->json('data.id');

        // Upload only 1 of 2 chunks
        $chunk = UploadedFile::fake()->create('chunk', 100);
        $this->apiPost("/api/uploads/{$uploadId}/chunk/0", [
            'chunk' => $chunk,
        ]);

        // Try to complete - should be incomplete
        $completeResponse = $this->apiPost("/api/uploads/{$uploadId}/complete");

        $completeResponse->assertStatus(200);
        $completeResponse->assertJsonPath('data.status', 'incomplete');

        // No ingest should be triggered
        Queue::assertNotPushed(ProcessIngestRunJob::class);
        $this->assertDatabaseMissing('ingest_runs', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function existing_upload_behavior_is_preserved_for_direct_upload(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->apiPost('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        // Original fields still present
        $response->assertJsonStructure([
            'data' => ['path', 'url', 'size', 'mime', 'name'],
        ]);
    }
}
