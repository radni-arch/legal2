<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EvidenceAssetControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('public');
    }

    /** @test */
    public function it_serves_file_with_valid_signed_url(): void
    {
        // Arrange
        $filePath = public_path('test-evidence.pdf');
        file_put_contents($filePath, 'PDF content');

        $encryptedPath = Crypt::encryptString($filePath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('PDF content', $response->getContent());

        // Cleanup
        unlink($filePath);
    }

    /** @test */
    public function it_rejects_request_without_signature(): void
    {
        // Arrange
        $filePath = public_path('test.pdf');
        $encryptedPath = Crypt::encryptString($filePath);

        // Act - Request without signature
        $response = $this->actingAs($this->user)->get("/evidence/asset?p={$encryptedPath}");

        // Assert
        $response->assertStatus(403); // Forbidden - invalid signature
    }

    /** @test */
    public function it_rejects_expired_signed_url(): void
    {
        // Arrange
        $filePath = public_path('test.pdf');
        file_put_contents($filePath, 'content');

        $encryptedPath = Crypt::encryptString($filePath);

        // Create an expired signed URL (expired 1 hour ago)
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->subHour(),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(403); // Signature expired

        // Cleanup
        unlink($filePath);
    }

    /** @test */
    public function it_returns_404_for_non_existent_file(): void
    {
        // Arrange
        $nonExistentPath = public_path('non-existent-file.pdf');
        $encryptedPath = Crypt::encryptString($nonExistentPath);

        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_prevents_directory_traversal_attacks(): void
    {
        // Arrange - Try to access a file outside allowed directories
        $maliciousPath = '/etc/passwd';
        $encryptedPath = Crypt::encryptString($maliciousPath);

        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404); // Not allowed
    }

    /** @test */
    public function it_prevents_access_to_files_outside_allowed_directories(): void
    {
        // Arrange - Create a file in a disallowed location
        $disallowedPath = base_path('composer.json'); // Outside allowed directories
        $encryptedPath = Crypt::encryptString($disallowedPath);

        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404); // Not in allowed directories
    }

    /** @test */
    public function it_allows_access_to_public_directory_files(): void
    {
        // Arrange
        $publicFile = public_path('allowed-file.txt');
        file_put_contents($publicFile, 'Public content');

        $encryptedPath = Crypt::encryptString($publicFile);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Public content', $response->getContent());

        // Cleanup
        unlink($publicFile);
    }

    /** @test */
    public function it_allows_access_to_storage_app_public_files(): void
    {
        // Arrange
        $storagePath = storage_path('app/public/test-file.pdf');

        // Ensure directory exists
        if (! is_dir(dirname($storagePath))) {
            mkdir(dirname($storagePath), 0755, true);
        }

        file_put_contents($storagePath, 'Storage public content');

        $encryptedPath = Crypt::encryptString($storagePath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Storage public content', $response->getContent());

        // Cleanup
        unlink($storagePath);
    }

    /** @test */
    public function it_allows_access_to_storage_app_private_files(): void
    {
        // Arrange
        $privatePath = storage_path('app/private/confidential.pdf');

        // Ensure directory exists
        if (! is_dir(dirname($privatePath))) {
            mkdir(dirname($privatePath), 0755, true);
        }

        file_put_contents($privatePath, 'Private content');

        $encryptedPath = Crypt::encryptString($privatePath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Private content', $response->getContent());

        // Cleanup
        unlink($privatePath);
    }

    /** @test */
    public function it_serves_pdf_files(): void
    {
        // Arrange
        $pdfPath = public_path('document.pdf');
        $pdfContent = '%PDF-1.4 fake pdf content';
        file_put_contents($pdfPath, $pdfContent);

        $encryptedPath = Crypt::encryptString($pdfPath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals($pdfContent, $response->getContent());

        // Cleanup
        unlink($pdfPath);
    }

    /** @test */
    public function it_serves_image_files(): void
    {
        // Arrange
        $imagePath = public_path('evidence.jpg');
        $imageContent = 'fake image binary data';
        file_put_contents($imagePath, $imageContent);

        $encryptedPath = Crypt::encryptString($imagePath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals($imageContent, $response->getContent());

        // Cleanup
        unlink($imagePath);
    }

    /** @test */
    public function it_serves_text_files(): void
    {
        // Arrange
        $textPath = public_path('evidence.txt');
        file_put_contents($textPath, 'Evidence text content');

        $encryptedPath = Crypt::encryptString($textPath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Evidence text content', $response->getContent());

        // Cleanup
        unlink($textPath);
    }

    /** @test */
    public function it_handles_path_with_special_characters(): void
    {
        // Arrange
        $specialPath = public_path('evidence file (2023).pdf');
        file_put_contents($specialPath, 'Special name content');

        $encryptedPath = Crypt::encryptString($specialPath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Special name content', $response->getContent());

        // Cleanup
        unlink($specialPath);
    }

    /** @test */
    public function it_prevents_symlink_attacks(): void
    {
        // Arrange - Try to access via symlink to disallowed location
        $disallowedFile = '/tmp/secret-file.txt';
        file_put_contents($disallowedFile, 'Secret content');

        $symlinkPath = public_path('symlink-test');
        @symlink($disallowedFile, $symlinkPath);

        $encryptedPath = Crypt::encryptString($symlinkPath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404); // Symlink resolves to disallowed location

        // Cleanup
        @unlink($symlinkPath);
        @unlink($disallowedFile);
    }

    /** @test */
    public function it_rejects_directory_paths(): void
    {
        // Arrange - Try to access a directory instead of a file
        $dirPath = public_path('test-directory');
        if (! is_dir($dirPath)) {
            mkdir($dirPath);
        }

        $encryptedPath = Crypt::encryptString($dirPath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(404); // Directories not allowed

        // Cleanup
        rmdir($dirPath);
    }

    /** @test */
    public function it_handles_url_encoded_encrypted_path(): void
    {
        // Arrange
        $filePath = public_path('test-encoded.pdf');
        file_put_contents($filePath, 'Encoded test content');

        $encryptedPath = Crypt::encryptString($filePath);
        $encodedEncryptedPath = urlencode($encryptedPath);

        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Encoded test content', $response->getContent());

        // Cleanup
        unlink($filePath);
    }

    /** @test */
    public function it_prevents_access_with_tampered_encrypted_path(): void
    {
        // Arrange
        $filePath = public_path('test.pdf');
        file_put_contents($filePath, 'content');

        $encryptedPath = Crypt::encryptString($filePath);
        $tamperedPath = $encryptedPath.'tampered';

        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $tamperedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(500); // Decryption fails

        // Cleanup
        unlink($filePath);
    }

    /** @test */
    public function it_serves_large_files(): void
    {
        // Arrange
        $largePath = public_path('large-evidence.bin');
        $largeContent = str_repeat('A', 10000); // 10KB of content
        file_put_contents($largePath, $largeContent);

        $encryptedPath = Crypt::encryptString($largePath);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals($largeContent, $response->getContent());

        // Cleanup
        unlink($largePath);
    }

    /** @test */
    public function it_handles_nested_directory_paths(): void
    {
        // Arrange
        $nestedDir = public_path('evidence/2024/january');
        if (! is_dir($nestedDir)) {
            mkdir($nestedDir, 0755, true);
        }

        $nestedFile = $nestedDir.'/case-file.pdf';
        file_put_contents($nestedFile, 'Nested file content');

        $encryptedPath = Crypt::encryptString($nestedFile);
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            ['p' => $encryptedPath]
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('Nested file content', $response->getContent());

        // Cleanup
        unlink($nestedFile);
        rmdir(public_path('evidence/2024/january'));
        rmdir(public_path('evidence/2024'));
        rmdir(public_path('evidence'));
    }

    /** @test */
    public function it_requires_encrypted_path_parameter(): void
    {
        // Arrange - Create signed URL without the 'p' parameter
        $signedUrl = URL::temporarySignedRoute(
            'evidence.asset',
            now()->addMinutes(30),
            []
        );

        // Act
        $response = $this->actingAs($this->user)->get($signedUrl);

        // Assert
        $response->assertStatus(500); // Missing 'p' parameter causes decryption error
    }

    /** @test */
    public function it_serves_files_with_different_extensions(): void
    {
        // Arrange
        $extensions = ['pdf', 'jpg', 'png', 'docx', 'txt', 'csv', 'json'];

        foreach ($extensions as $ext) {
            $filePath = public_path("file.{$ext}");
            file_put_contents($filePath, "Content for {$ext}");

            $encryptedPath = Crypt::encryptString($filePath);
            $signedUrl = URL::temporarySignedRoute(
                'evidence.asset',
                now()->addMinutes(30),
                ['p' => $encryptedPath]
            );

            // Act
            $response = $this->actingAs($this->user)->get($signedUrl);

            // Assert
            $response->assertStatus(200);
            $this->assertEquals("Content for {$ext}", $response->getContent());

            // Cleanup
            unlink($filePath);
        }
    }
}
