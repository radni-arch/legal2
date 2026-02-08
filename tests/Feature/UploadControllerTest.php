<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class UploadControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_direct_upload_stores_file_on_public_disk(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/uploads', [
            'file' => UploadedFile::fake()->createWithContent('hello.txt', 'hello world'),
        ]);

        $res->assertOk()->assertJsonStructure(['path', 'url', 'size', 'mime', 'name']);
        $path = $res->json('path');
        Storage::disk('public')->assertExists($path);
    }

    /** @test */
    public function test_chunked_upload_flow_complete(): void
    {
        $start = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'big.txt',
            'totalSize' => 6,
            'chunkSize' => 3,
        ])->assertOk();
        $id = $start->json('id');

        $this->actingAs($this->user)->postJson("/api/uploads/{$id}/chunk/0", [
            'chunk' => UploadedFile::fake()->createWithContent('part0', 'foo'),
        ])->assertOk();

        $this->actingAs($this->user)->postJson("/api/uploads/{$id}/chunk/1", [
            'chunk' => UploadedFile::fake()->createWithContent('part1', 'bar'),
        ])->assertOk();

        $done = $this->actingAs($this->user)->postJson("/api/uploads/{$id}/complete")->assertOk();
        $done->assertJsonFragment(['status' => 'completed']);

        $path = $done->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertSame('foobar', Storage::disk('public')->get($path));
    }

    /** @test */
    public function test_chunked_upload_incomplete_when_missing_parts(): void
    {
        $start = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'big.txt',
            'totalSize' => 6,
            'chunkSize' => 3,
        ])->assertOk();
        $id = $start->json('id');

        $this->actingAs($this->user)->postJson("/api/uploads/{$id}/chunk/0", [
            'chunk' => UploadedFile::fake()->createWithContent('part0', 'foo'),
        ])->assertOk();

        $done = $this->actingAs($this->user)->postJson("/api/uploads/{$id}/complete")->assertOk();
        $done->assertJsonFragment(['status' => 'incomplete']);
        $this->assertSame(2, $done->json('expected'));
    }

    /** @test */
    public function it_validates_required_file_for_direct_upload(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_uploads_pdf_file_directly(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'path',
                'url',
                'size',
                'mime',
                'name',
            ])
            ->assertJson([
                'name' => 'document.pdf',
                'mime' => 'application/pdf',
            ]);

        Storage::disk('public')->assertExists($response->json('path'));
    }

    /** @test */
    public function it_uploads_image_directly(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($this->user)->postJson('/api/uploads', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'photo.jpg',
            ]);

        $this->assertStringContainsString('image/', $response->json('mime'));
        Storage::disk('public')->assertExists($response->json('path'));
    }

    /** @test */
    public function it_validates_required_fields_for_chunked_upload_start(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads/start', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filename', 'totalSize', 'chunkSize']);
    }

    /** @test */
    public function it_validates_minimum_values_for_chunked_upload(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'test.pdf',
            'totalSize' => 0,
            'chunkSize' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['totalSize', 'chunkSize']);
    }

    /** @test */
    public function it_validates_chunk_file_is_required(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'document.pdf',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
        ]);

        $uploadId = $startResponse->json('id');

        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chunk']);
    }

    /** @test */
    public function it_uploads_multiple_chunks_in_sequence(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'document.pdf',
            'totalSize' => 9,
            'chunkSize' => 3,
        ]);

        $uploadId = $startResponse->json('id');

        $chunk1 = UploadedFile::fake()->createWithContent('chunk1', 'abc');
        $response1 = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk1]);

        $chunk2 = UploadedFile::fake()->createWithContent('chunk2', 'def');
        $response2 = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/1", ['chunk' => $chunk2]);

        $chunk3 = UploadedFile::fake()->createWithContent('chunk3', 'ghi');
        $response3 = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/2", ['chunk' => $chunk3]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $response3->assertStatus(200);

        $this->assertEquals([0], $response1->json('received'));
        $this->assertEquals([0, 1], $response2->json('received'));
        $this->assertEquals([0, 1, 2], $response3->json('received'));
    }

    /** @test */
    public function it_handles_out_of_order_chunk_uploads(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'document.pdf',
            'totalSize' => 9,
            'chunkSize' => 3,
        ]);

        $uploadId = $startResponse->json('id');

        $chunk2 = UploadedFile::fake()->createWithContent('chunk2', 'def');
        $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/1", ['chunk' => $chunk2]);

        $chunk0 = UploadedFile::fake()->createWithContent('chunk0', 'abc');
        $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk0]);

        $chunk1 = UploadedFile::fake()->createWithContent('chunk1', 'ghi');
        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/2", ['chunk' => $chunk1]);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('received'));
    }

    /** @test */
    public function it_cancels_chunked_upload(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'cancel-test.pdf',
            'totalSize' => 6,
            'chunkSize' => 3,
        ]);

        $uploadId = $startResponse->json('id');

        $chunk = UploadedFile::fake()->createWithContent('chunk', 'foo');
        $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk]);

        $response = $this->actingAs($this->user)->deleteJson("/api/uploads/{$uploadId}");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ]);
    }

    /** @test */
    public function it_handles_invalid_upload_id_for_chunk_upload(): void
    {
        $invalidUploadId = 'invalid-upload-id';
        $chunk = UploadedFile::fake()->createWithContent('chunk', 'test');

        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$invalidUploadId}/chunk/0", [
            'chunk' => $chunk,
        ]);

        $response->assertStatus(500);
    }

    /** @test */
    public function it_handles_invalid_upload_id_for_complete(): void
    {
        $invalidUploadId = 'invalid-upload-id';

        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$invalidUploadId}/complete");

        $response->assertStatus(500);
    }

    /** @test */
    public function it_accepts_optional_mime_type_in_start(): void
    {
        $response1 = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'test1.pdf',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
        ]);

        $response2 = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'test2.pdf',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
            'mime' => 'application/pdf',
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200)
            ->assertJson(['mime' => 'application/pdf']);
    }

    /** @test */
    public function it_creates_unique_upload_ids_for_multiple_uploads(): void
    {
        $response1 = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'file1.pdf',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
        ]);

        $response2 = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'file2.pdf',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $id1 = $response1->json('id');
        $id2 = $response2->json('id');

        $this->assertNotEquals($id1, $id2);
        $this->assertNotEmpty($id1);
        $this->assertNotEmpty($id2);
    }

    /** @test */
    public function it_uploads_different_file_types_directly(): void
    {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        $imageFile = UploadedFile::fake()->image('photo.jpg');
        $textFile = UploadedFile::fake()->create('readme.txt', 100, 'text/plain');

        $pdfResponse = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => $pdfFile]);
        $imageResponse = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => $imageFile]);
        $textResponse = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => $textFile]);

        $pdfResponse->assertStatus(200)->assertJson(['mime' => 'application/pdf']);
        $imageResponse->assertStatus(200);
        $textResponse->assertStatus(200)->assertJson(['mime' => 'text/plain']);

        Storage::disk('public')->assertExists($pdfResponse->json('path'));
        Storage::disk('public')->assertExists($imageResponse->json('path'));
        Storage::disk('public')->assertExists($textResponse->json('path'));
    }

    /** @test */
    public function it_returns_file_size_in_direct_upload_response(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 2048);

        $response = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertIsInt($response->json('size'));
        $this->assertGreaterThan(0, $response->json('size'));
    }

    /** @test */
    public function it_returns_url_in_direct_upload_response(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('url'));
        $this->assertStringContainsString('storage', $response->json('url'));
    }

    /** @test */
    public function it_starts_chunked_upload_with_all_metadata(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'large-document.pdf',
            'totalSize' => 10485760,
            'chunkSize' => 1048576,
            'mime' => 'application/pdf',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'filename',
                'mime',
                'total_size',
                'chunk_size',
                'created_at',
                'received',
                'completed',
            ])
            ->assertJson([
                'filename' => 'large-document.pdf',
                'mime' => 'application/pdf',
                'total_size' => 10485760,
                'chunk_size' => 1048576,
                'completed' => false,
            ]);

        $this->assertIsArray($response->json('received'));
        $this->assertEmpty($response->json('received'));
    }

    /** @test */
    public function it_tracks_received_chunks_correctly(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'test.pdf',
            'totalSize' => 6,
            'chunkSize' => 2,
        ]);

        $uploadId = $startResponse->json('id');

        $chunk0 = UploadedFile::fake()->createWithContent('c0', 'ab');
        $response0 = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk0]);

        $chunk1 = UploadedFile::fake()->createWithContent('c1', 'cd');
        $response1 = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/1", ['chunk' => $chunk1]);

        $response0->assertJson(['received' => [0]]);
        $response1->assertJson(['received' => [0, 1]]);
    }

    /** @test */
    public function it_sanitizes_filename_with_special_characters(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => '../../../etc/passwd',
            'totalSize' => 1048576,
            'chunkSize' => 524288,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('passwd', $response->json('filename'));
    }

    /** @test */
    public function it_handles_large_chunk_sizes(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'very-large-file.zip',
            'totalSize' => 5368709120,
            'chunkSize' => 10485760,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'total_size' => 5368709120,
                'chunk_size' => 10485760,
            ]);
    }

    /** @test */
    public function it_returns_upload_id_and_index_in_chunk_response(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'test.pdf',
            'totalSize' => 3,
            'chunkSize' => 1,
        ]);

        $uploadId = $startResponse->json('id');
        $chunk = UploadedFile::fake()->createWithContent('chunk', 'a');

        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $uploadId,
                'index' => 0,
            ]);
    }

    /** @test */
    public function it_returns_completed_upload_metadata(): void
    {
        $startResponse = $this->actingAs($this->user)->postJson('/api/uploads/start', [
            'filename' => 'complete.pdf',
            'totalSize' => 3,
            'chunkSize' => 3,
        ]);

        $uploadId = $startResponse->json('id');

        $chunk = UploadedFile::fake()->createWithContent('chunk', 'abc');
        $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/chunk/0", ['chunk' => $chunk]);

        $response = $this->actingAs($this->user)->postJson("/api/uploads/{$uploadId}/complete");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'id',
                'filename',
                'path',
                'url',
            ])
            ->assertJson([
                'status' => 'completed',
                'id' => $uploadId,
                'filename' => 'complete.pdf',
            ]);

        $this->assertNotNull($response->json('path'));
        $this->assertNotNull($response->json('url'));
        Storage::disk('public')->assertExists($response->json('path'));
    }
}
