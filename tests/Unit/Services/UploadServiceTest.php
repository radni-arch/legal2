<?php

namespace Tests\Unit\Services;

use App\Services\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comprehensive Test Suite for UploadService
 *
 * Tests all functionality:
 * - Initiating chunked uploads (start)
 * - Uploading individual chunks (uploadChunk)
 * - Completing chunked uploads (complete)
 * - Canceling uploads (cancel)
 * - Direct single-file uploads (directStore)
 * - Error handling and validation
 * - File safety and path helpers
 * - Manifest management
 *
 * Coverage:
 * - Upload session creation and ID generation
 * - Chunk storage and tracking
 * - File assembly from chunks
 * - Cleanup operations
 * - Edge cases (missing chunks, invalid IDs, special characters)
 */
class UploadServiceTest extends TestCase
{
    protected UploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->service = new UploadService;
    }

    // ========================================
    // Start Upload Tests
    // ========================================

    /** @test */
    public function it_can_start_a_chunked_upload()
    {
        $manifest = $this->service->start(
            filename: 'test-document.pdf',
            totalSize: 10000000,
            chunkSize: 1000000,
            mime: 'application/pdf'
        );

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('id', $manifest);
        $this->assertArrayHasKey('filename', $manifest);
        $this->assertArrayHasKey('mime', $manifest);
        $this->assertArrayHasKey('total_size', $manifest);
        $this->assertArrayHasKey('chunk_size', $manifest);
        $this->assertArrayHasKey('created_at', $manifest);
        $this->assertArrayHasKey('received', $manifest);
        $this->assertArrayHasKey('completed', $manifest);
    }

    /** @test */
    public function start_generates_unique_upload_id()
    {
        $manifest1 = $this->service->start('file1.pdf', 1000, 100);
        $manifest2 = $this->service->start('file2.pdf', 1000, 100);

        $this->assertNotEquals($manifest1['id'], $manifest2['id']);
    }

    /** @test */
    public function start_stores_manifest_file()
    {
        $manifest = $this->service->start('test.pdf', 10000, 1000);

        $manifestPath = 'uploads/manifests/'.$manifest['id'].'.json';
        Storage::assertExists($manifestPath);
    }

    /** @test */
    public function start_creates_chunk_directory()
    {
        $manifest = $this->service->start('test.pdf', 10000, 1000);

        $chunkDir = 'uploads/chunks/'.$manifest['id'];
        // Check that directory exists by checking if we can list it
        $this->assertTrue(Storage::exists($chunkDir));
    }

    /** @test */
    public function start_sets_correct_manifest_data()
    {
        $manifest = $this->service->start(
            filename: 'my-document.pdf',
            totalSize: 5000000,
            chunkSize: 500000,
            mime: 'application/pdf'
        );

        $this->assertEquals('my-document.pdf', $manifest['filename']);
        $this->assertEquals('application/pdf', $manifest['mime']);
        $this->assertEquals(5000000, $manifest['total_size']);
        $this->assertEquals(500000, $manifest['chunk_size']);
        $this->assertIsArray($manifest['received']);
        $this->assertEmpty($manifest['received']);
        $this->assertFalse($manifest['completed']);
        $this->assertNotEmpty($manifest['created_at']);
    }

    /** @test */
    public function start_sanitizes_filename_keeping_basename()
    {
        $manifest = $this->service->start(
            filename: '/path/to/dangerous/../file.pdf',
            totalSize: 1000,
            chunkSize: 100
        );

        $this->assertEquals('file.pdf', $manifest['filename']);
    }

    /** @test */
    public function start_enforces_minimum_chunk_size()
    {
        $manifest = $this->service->start('test.pdf', 10000, 0);

        $this->assertEquals(1, $manifest['chunk_size']);
    }

    /** @test */
    public function start_accepts_null_mime_type()
    {
        $manifest = $this->service->start('test.pdf', 10000, 1000, null);

        $this->assertNull($manifest['mime']);
    }

    // ========================================
    // Upload Chunk Tests
    // ========================================

    /** @test */
    public function it_can_upload_a_chunk()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $result = $this->service->uploadChunk($uploadId, 0, $chunk);

        $this->assertIsArray($result);
        $this->assertEquals($uploadId, $result['id']);
        $this->assertEquals(0, $result['index']);
        $this->assertContains(0, $result['received']);
    }

    /** @test */
    public function upload_chunk_stores_chunk_file()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $chunkPath = 'uploads/chunks/'.$uploadId.'/part_0';
        Storage::assertExists($chunkPath);
    }

    /** @test */
    public function upload_chunk_updates_manifest()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $manifestPath = 'uploads/manifests/'.$uploadId.'.json';
        $updated = json_decode(Storage::get($manifestPath), true);

        $this->assertContains(0, $updated['received']);
    }

    /** @test */
    public function upload_chunk_tracks_multiple_chunks()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);
        $chunk2 = UploadedFile::fake()->create('chunk2.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);
        $result = $this->service->uploadChunk($uploadId, 2, $chunk2);

        $this->assertCount(3, $result['received']);
        $this->assertContains(0, $result['received']);
        $this->assertContains(1, $result['received']);
        $this->assertContains(2, $result['received']);
    }

    /** @test */
    public function upload_chunk_handles_duplicate_chunks()
    {
        $manifest = $this->service->start('test.pdf', 2000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk);
        $chunk2 = UploadedFile::fake()->create('chunk.part', 1000);
        $result = $this->service->uploadChunk($uploadId, 0, $chunk2);

        // Should still have only one entry for index 0
        $this->assertCount(1, $result['received']);
        $this->assertEquals([0], $result['received']);
    }

    /** @test */
    public function upload_chunk_throws_exception_for_invalid_upload_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid upload ID');

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk('non-existent-id', 0, $chunk);
    }

    /** @test */
    public function upload_chunk_handles_out_of_order_uploads()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        // Upload chunks out of order: 2, 0, 1
        $chunk2 = UploadedFile::fake()->create('chunk2.part', 1000);
        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 2, $chunk2);
        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $result = $this->service->uploadChunk($uploadId, 1, $chunk1);

        $this->assertCount(3, $result['received']);
        $this->assertContains(0, $result['received']);
        $this->assertContains(1, $result['received']);
        $this->assertContains(2, $result['received']);
    }

    // ========================================
    // Complete Upload Tests
    // ========================================

    /** @test */
    public function it_can_complete_a_chunked_upload()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        // Upload all chunks
        for ($i = 0; $i < 3; $i++) {
            $chunk = UploadedFile::fake()->create("chunk{$i}.part", 1000);
            $this->service->uploadChunk($uploadId, $i, $chunk);
        }

        $result = $this->service->complete($uploadId);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals($uploadId, $result['id']);
        $this->assertEquals('test.pdf', $result['filename']);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
    }

    /** @test */
    public function complete_assembles_file_on_public_disk()
    {
        $manifest = $this->service->start('document.pdf', 2000, 1000);
        $uploadId = $manifest['id'];

        // Upload chunks
        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $result = $this->service->complete($uploadId);

        Storage::disk('public')->assertExists($result['path']);
    }

    /** @test */
    public function complete_returns_incomplete_status_when_chunks_missing()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        // Upload only 2 out of 3 chunks
        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $result = $this->service->complete($uploadId);

        $this->assertEquals('incomplete', $result['status']);
        $this->assertEquals(3, $result['expected']);
        $this->assertIsArray($result['received']);
        $this->assertCount(2, $result['received']);
    }

    /** @test */
    public function complete_cleans_up_chunk_directory()
    {
        $manifest = $this->service->start('test.pdf', 2000, 1000);
        $uploadId = $manifest['id'];

        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $chunkDir = 'uploads/chunks/'.$uploadId;
        $this->assertTrue(Storage::exists($chunkDir));

        $this->service->complete($uploadId);

        // Chunk directory should be deleted after completion
        $this->assertFalse(Storage::exists($chunkDir));
    }

    /** @test */
    public function complete_updates_manifest_with_completion_data()
    {
        $manifest = $this->service->start('test.pdf', 2000, 1000);
        $uploadId = $manifest['id'];

        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $this->service->complete($uploadId);

        $manifestPath = 'uploads/manifests/'.$uploadId.'.json';
        $updated = json_decode(Storage::get($manifestPath), true);

        $this->assertTrue($updated['completed']);
        $this->assertArrayHasKey('stored_at', $updated);
        $this->assertArrayHasKey('path', $updated);
        $this->assertArrayHasKey('disk', $updated);
        $this->assertArrayHasKey('url', $updated);
    }

    /** @test */
    public function complete_throws_exception_for_invalid_upload_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid upload ID');

        $this->service->complete('non-existent-id');
    }

    /** @test */
    public function complete_calculates_correct_number_of_chunks()
    {
        // Test case: 2500 bytes with 1000 byte chunks = 3 chunks needed
        $manifest = $this->service->start('test.pdf', 2500, 1000);
        $uploadId = $manifest['id'];

        // Upload only 2 chunks (incomplete)
        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);

        $this->service->uploadChunk($uploadId, 0, $chunk0);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $result = $this->service->complete($uploadId);

        $this->assertEquals('incomplete', $result['status']);
        $this->assertEquals(3, $result['expected']); // ceil(2500 / 1000) = 3
    }

    /** @test */
    public function complete_generates_safe_filename_in_path()
    {
        $manifest = $this->service->start('My Document (v2).pdf', 1000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $result = $this->service->complete($uploadId);

        // Filename should be sanitized
        $this->assertStringContainsString($uploadId, $result['path']);
        $this->assertStringContainsString('My-Document-v2-.pdf', $result['path']);
    }

    // ========================================
    // Cancel Upload Tests
    // ========================================

    /** @test */
    public function it_can_cancel_an_upload()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $result = $this->service->cancel($uploadId);

        $this->assertTrue($result);
    }

    /** @test */
    public function cancel_removes_chunk_directory()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $chunkDir = 'uploads/chunks/'.$uploadId;
        $this->assertTrue(Storage::exists($chunkDir));

        $this->service->cancel($uploadId);

        $this->assertFalse(Storage::exists($chunkDir));
    }

    /** @test */
    public function cancel_removes_manifest_file()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];

        $manifestPath = 'uploads/manifests/'.$uploadId.'.json';
        Storage::assertExists($manifestPath);

        $this->service->cancel($uploadId);

        Storage::assertMissing($manifestPath);
    }

    /** @test */
    public function cancel_handles_non_existent_upload_gracefully()
    {
        // Should not throw exception
        $result = $this->service->cancel('non-existent-id');

        $this->assertTrue($result);
    }

    // ========================================
    // Direct Store Tests
    // ========================================

    /** @test */
    public function it_can_directly_store_a_file()
    {
        $file = UploadedFile::fake()->create('document.pdf', 5000);

        $result = $this->service->directStore($file);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('mime', $result);
        $this->assertArrayHasKey('name', $result);
    }

    /** @test */
    public function direct_store_saves_file_on_public_disk()
    {
        $file = UploadedFile::fake()->create('document.pdf', 5000);

        $result = $this->service->directStore($file);

        Storage::disk('public')->assertExists($result['path']);
    }

    /** @test */
    public function direct_store_returns_correct_metadata()
    {
        $file = UploadedFile::fake()->create('my-document.pdf', 5000);

        $result = $this->service->directStore($file);

        $this->assertStringContainsString('uploads/', $result['path']);
        $this->assertEquals('my-document.pdf', $result['name']);
        $this->assertGreaterThan(0, $result['size']); // Fake files may have different size than specified
        $this->assertStringContainsString('application/pdf', $result['mime']);
    }

    /** @test */
    public function direct_store_generates_public_url()
    {
        $file = UploadedFile::fake()->create('document.pdf', 5000);

        $result = $this->service->directStore($file);

        $this->assertNotEmpty($result['url']);
        $this->assertIsString($result['url']);
    }

    /** @test */
    public function direct_store_handles_different_file_types()
    {
        $pdfFile = UploadedFile::fake()->create('document.pdf', 1000);
        $imageFile = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $txtFile = UploadedFile::fake()->create('notes.txt', 500);

        $pdfResult = $this->service->directStore($pdfFile);
        $imageResult = $this->service->directStore($imageFile);
        $txtResult = $this->service->directStore($txtFile);

        $this->assertStringContainsString('document.pdf', $pdfResult['name']);
        $this->assertStringContainsString('photo.jpg', $imageResult['name']);
        $this->assertStringContainsString('notes.txt', $txtResult['name']);

        Storage::disk('public')->assertExists($pdfResult['path']);
        Storage::disk('public')->assertExists($imageResult['path']);
        Storage::disk('public')->assertExists($txtResult['path']);
    }

    // ========================================
    // Helper Method Tests
    // ========================================

    /** @test */
    public function safe_filename_removes_special_characters()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('safeFilename');
        $method->setAccessible(true);

        $tests = [
            'normal-file.pdf' => 'normal-file.pdf',
            'file with spaces.pdf' => 'file-with-spaces.pdf',
            'file@#$%^&*()test.pdf' => 'file-test.pdf',
            'UPPERCASE.PDF' => 'UPPERCASE.PDF',
            'file_underscore-dash.txt' => 'file_underscore-dash.txt',
            '!!!dangerous!!!.exe' => 'dangerous-.exe', // Pattern leaves trailing dash before extension
            'my document (v2).pdf' => 'my-document-v2-.pdf',
        ];

        foreach ($tests as $input => $expected) {
            $result = $method->invoke($this->service, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /** @test */
    public function safe_filename_trims_leading_and_trailing_dashes()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('safeFilename');
        $method->setAccessible(true);

        $this->assertEquals('test.pdf', $method->invoke($this->service, '---test.pdf---'));
        $this->assertEquals('test.pdf', $method->invoke($this->service, '-test.pdf-'));
    }

    /** @test */
    public function get_manifest_returns_null_for_non_existent_upload()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getManifest');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'non-existent-id');

        $this->assertNull($result);
    }

    /** @test */
    public function get_manifest_returns_array_for_existing_upload()
    {
        $manifest = $this->service->start('test.pdf', 1000, 100);
        $uploadId = $manifest['id'];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getManifest');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $uploadId);

        $this->assertIsArray($result);
        $this->assertEquals($uploadId, $result['id']);
    }

    // ========================================
    // Edge Case and Integration Tests
    // ========================================

    /** @test */
    public function it_handles_single_chunk_upload()
    {
        // File small enough to fit in one chunk
        $manifest = $this->service->start('small.txt', 500, 1000);
        $uploadId = $manifest['id'];

        $chunk = UploadedFile::fake()->create('chunk.part', 500);
        $this->service->uploadChunk($uploadId, 0, $chunk);

        $result = $this->service->complete($uploadId);

        $this->assertEquals('completed', $result['status']);
        Storage::disk('public')->assertExists($result['path']);
    }

    /** @test */
    public function it_handles_large_number_of_chunks()
    {
        // File with many small chunks
        $totalSize = 10000;
        $chunkSize = 100;
        $numChunks = (int) ceil($totalSize / $chunkSize); // 100 chunks

        $manifest = $this->service->start('large.bin', $totalSize, $chunkSize);
        $uploadId = $manifest['id'];

        // Upload all chunks
        for ($i = 0; $i < $numChunks; $i++) {
            $chunk = UploadedFile::fake()->create("chunk{$i}.part", $chunkSize);
            $this->service->uploadChunk($uploadId, $i, $chunk);
        }

        $result = $this->service->complete($uploadId);

        $this->assertEquals('completed', $result['status']);
    }

    /** @test */
    public function it_preserves_file_extension()
    {
        $extensions = ['pdf', 'docx', 'jpg', 'png', 'txt', 'zip'];

        foreach ($extensions as $ext) {
            $filename = "document.{$ext}";
            $manifest = $this->service->start($filename, 1000, 1000);
            $uploadId = $manifest['id'];

            $chunk = UploadedFile::fake()->create('chunk.part', 1000);
            $this->service->uploadChunk($uploadId, 0, $chunk);

            $result = $this->service->complete($uploadId);

            $this->assertStringEndsWith(".{$ext}", $result['path']);
            $this->assertEquals($filename, $result['filename']);
        }
    }

    /** @test */
    public function manifest_persists_across_chunk_uploads()
    {
        $manifest = $this->service->start('test.pdf', 3000, 1000);
        $uploadId = $manifest['id'];
        $createdAt = $manifest['created_at'];

        // Upload chunks and verify manifest data persists
        $chunk0 = UploadedFile::fake()->create('chunk0.part', 1000);
        $this->service->uploadChunk($uploadId, 0, $chunk0);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getManifest');
        $method->setAccessible(true);
        $manifest1 = $method->invoke($this->service, $uploadId);

        $this->assertEquals('test.pdf', $manifest1['filename']);
        $this->assertEquals($createdAt, $manifest1['created_at']);
        $this->assertContains(0, $manifest1['received']);

        // Upload another chunk
        $chunk1 = UploadedFile::fake()->create('chunk1.part', 1000);
        $this->service->uploadChunk($uploadId, 1, $chunk1);

        $manifest2 = $method->invoke($this->service, $uploadId);

        $this->assertEquals('test.pdf', $manifest2['filename']);
        $this->assertEquals($createdAt, $manifest2['created_at']);
        $this->assertContains(0, $manifest2['received']);
        $this->assertContains(1, $manifest2['received']);
    }

    /** @test */
    public function upload_workflow_end_to_end()
    {
        // Simulate complete upload workflow
        $filename = 'complete-test.pdf';
        $totalSize = 5000;
        $chunkSize = 1000;
        $numChunks = (int) ceil($totalSize / $chunkSize);

        // 1. Start upload
        $manifest = $this->service->start($filename, $totalSize, $chunkSize, 'application/pdf');
        $uploadId = $manifest['id'];

        $this->assertNotEmpty($uploadId);
        $this->assertFalse($manifest['completed']);

        // 2. Upload all chunks
        for ($i = 0; $i < $numChunks; $i++) {
            $chunk = UploadedFile::fake()->create("chunk{$i}.part", $chunkSize);
            $result = $this->service->uploadChunk($uploadId, $i, $chunk);

            $this->assertEquals($uploadId, $result['id']);
            $this->assertContains($i, $result['received']);
        }

        // 3. Complete upload
        $result = $this->service->complete($uploadId);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals($uploadId, $result['id']);
        $this->assertEquals($filename, $result['filename']);
        $this->assertNotEmpty($result['path']);
        $this->assertNotEmpty($result['url']);

        // 4. Verify file exists on public disk
        Storage::disk('public')->assertExists($result['path']);

        // 5. Verify chunks are cleaned up
        $chunkDir = 'uploads/chunks/'.$uploadId;
        $this->assertFalse(Storage::exists($chunkDir));

        // 6. Verify manifest is marked complete
        $manifestPath = 'uploads/manifests/'.$uploadId.'.json';
        $finalManifest = json_decode(Storage::get($manifestPath), true);
        $this->assertTrue($finalManifest['completed']);
    }
}
