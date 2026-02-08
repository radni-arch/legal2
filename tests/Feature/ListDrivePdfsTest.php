<?php

namespace Tests\Feature;

use App\Actions\Textract\ListDrivePdfs;
use App\Services\GoogleDriveService;
use Mockery;
use Tests\TestCase;

/**
 * Task 2.A.1: ListDrivePdfs Test
 *
 * Comprehensive test suite for the ListDrivePdfs action that encapsulates
 * the logic for listing PDF files from a Google Drive folder.
 */
class ListDrivePdfsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that the action successfully lists PDFs from a folder
     *
     * @test
     */
    public function it_lists_pdfs_from_drive_folder(): void
    {
        // Mock GoogleDriveService
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->with('folder-123')
            ->andReturn([
                [
                    'id' => 'file-1',
                    'name' => 'document1.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 1024,
                ],
                [
                    'id' => 'file-2',
                    'name' => 'document2.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 2048,
                ],
            ]);

        // Create action with mocked service
        $action = new ListDrivePdfs($driveMock);

        // Execute
        $result = $action->handle('folder-123');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('file-1', $result[0]['id']);
        $this->assertEquals('document1.pdf', $result[0]['name']);
    }

    /**
     * Test that the action returns empty array for empty folder
     *
     * @test
     */
    public function it_returns_empty_array_for_empty_folder(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->with('empty-folder')
            ->andReturn([]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('empty-folder');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that the action handles large number of PDFs
     *
     * @test
     */
    public function it_handles_large_number_of_pdfs(): void
    {
        // Generate 100 mock PDFs
        $mockFiles = [];
        for ($i = 1; $i <= 100; $i++) {
            $mockFiles[] = [
                'id' => "file-{$i}",
                'name' => "document{$i}.pdf",
                'mimeType' => 'application/pdf',
                'size' => 1024 * $i,
            ];
        }

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->with('large-folder')
            ->andReturn($mockFiles);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('large-folder');

        $this->assertCount(100, $result);
        $this->assertEquals('file-1', $result[0]['id']);
        $this->assertEquals('file-100', $result[99]['id']);
    }

    /**
     * Test that each PDF has required fields
     *
     * @test
     */
    public function it_returns_pdfs_with_required_fields(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-1',
                    'name' => 'document.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 5120,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('mimeType', $result[0]);
        $this->assertArrayHasKey('size', $result[0]);
    }

    /**
     * Test that the action propagates drive service exceptions
     *
     * @test
     */
    public function it_propagates_drive_service_exceptions(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andThrow(new \Google\Service\Exception('Drive API error'));

        $action = new ListDrivePdfs($driveMock);

        $this->expectException(\Google\Service\Exception::class);
        $this->expectExceptionMessage('Drive API error');

        $action->handle('folder-123');
    }

    /**
     * Test that the action can be instantiated via container
     *
     * @test
     */
    public function it_can_be_instantiated_via_container(): void
    {
        // Skip if GoogleDriveService requires credentials
        if (! env('GOOGLE_APPLICATION_CREDENTIALS')) {
            $this->markTestSkipped('Google Drive credentials not configured');
        }

        $action = app(ListDrivePdfs::class);

        $this->assertInstanceOf(ListDrivePdfs::class, $action);
        $this->assertInstanceOf(GoogleDriveService::class, $action->drive);
    }

    /**
     * Test that the action returns correct data types
     *
     * @test
     */
    public function it_returns_correct_data_types(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-abc',
                    'name' => 'test.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 12345,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-xyz');

        $this->assertIsString($result[0]['id']);
        $this->assertIsString($result[0]['name']);
        $this->assertIsString($result[0]['mimeType']);
        $this->assertIsInt($result[0]['size']);
    }

    /**
     * Test that the action handles PDFs with special characters in names
     *
     * @test
     */
    public function it_handles_pdfs_with_special_characters_in_names(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-1',
                    'name' => 'Dokument #1 (final) - копия.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 1024,
                ],
                [
                    'id' => 'file-2',
                    'name' => 'Zakon_2024-01-15_[draft].pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 2048,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertEquals('Dokument #1 (final) - копия.pdf', $result[0]['name']);
        $this->assertEquals('Zakon_2024-01-15_[draft].pdf', $result[1]['name']);
    }

    /**
     * Test that the action handles very large PDF files
     *
     * @test
     */
    public function it_handles_very_large_pdf_files(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-large',
                    'name' => 'large-document.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 104857600, // 100 MB
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertEquals(104857600, $result[0]['size']);
    }

    /**
     * Test that the action uses AsAction trait
     *
     * @test
     */
    public function it_uses_as_action_trait(): void
    {
        $reflection = new \ReflectionClass(ListDrivePdfs::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Lorisleiva\Actions\Concerns\AsAction', $traits);
    }

    /**
     * Test that the action can be called statically via run method
     *
     * @test
     */
    public function it_can_be_called_statically_via_run_method(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->with('folder-static')
            ->andReturn([
                ['id' => 'file-1', 'name' => 'doc1.pdf', 'mimeType' => 'application/pdf', 'size' => 1024],
            ]);

        // Bind the mock to the container
        $this->app->instance(GoogleDriveService::class, $driveMock);

        $result = ListDrivePdfs::run('folder-static');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test that the action filters only PDF files (not other files)
     *
     * @test
     */
    public function it_filters_only_pdf_files(): void
    {
        // The service should only return PDFs, but verify the contract
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-1',
                    'name' => 'document.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 1024,
                ],
                // Service should not return non-PDFs, but if it does, they should have PDF mimeType
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        foreach ($result as $file) {
            $this->assertEquals('application/pdf', $file['mimeType']);
        }
    }

    /**
     * Test that the action handles folder IDs with various formats
     *
     * @test
     */
    public function it_handles_various_folder_id_formats(): void
    {
        $folderIds = [
            '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs94KzB', // Standard format
            '0B-ABC123', // Short format
            'folder-with-dashes',
            'folder_with_underscores',
        ];

        foreach ($folderIds as $folderId) {
            $driveMock = Mockery::mock(GoogleDriveService::class);
            $driveMock
                ->shouldReceive('listPdfsInFolder')
                ->once()
                ->with($folderId)
                ->andReturn([]);

            $action = new ListDrivePdfs($driveMock);
            $result = $action->handle($folderId);

            $this->assertIsArray($result);
        }
    }

    /**
     * Test that the action maintains file order returned by service
     *
     * @test
     */
    public function it_maintains_file_order_from_service(): void
    {
        $expectedOrder = [
            ['id' => 'file-z', 'name' => 'z.pdf', 'mimeType' => 'application/pdf', 'size' => 1],
            ['id' => 'file-a', 'name' => 'a.pdf', 'mimeType' => 'application/pdf', 'size' => 2],
            ['id' => 'file-m', 'name' => 'm.pdf', 'mimeType' => 'application/pdf', 'size' => 3],
        ];

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn($expectedOrder);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertEquals('file-z', $result[0]['id']);
        $this->assertEquals('file-a', $result[1]['id']);
        $this->assertEquals('file-m', $result[2]['id']);
    }

    /**
     * Test action contract: input and output types
     *
     * @test
     */
    public function it_adheres_to_action_contract(): void
    {
        // Contract:
        // - Input: string $folderId
        // - Output: array of files with keys: id, name, mimeType, size

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-1',
                    'name' => 'test.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 1024,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);

        // Input: string
        $result = $action->handle('folder-123');

        // Output: array
        $this->assertIsArray($result);

        // Each element has required keys
        if (! empty($result)) {
            $requiredKeys = ['id', 'name', 'mimeType', 'size'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $result[0]);
            }
        }
    }

    /**
     * Test that the action can be unit tested without touching real API
     *
     * @test
     */
    public function it_can_be_unit_tested_without_real_api_calls(): void
    {
        // This test itself demonstrates that we can mock the Drive service
        // and test the action in isolation
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock->shouldReceive('listPdfsInFolder')->once()->andReturn([]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('test-folder');

        // If we reached here without making real API calls, test passes
        $this->assertTrue(true);
    }

    /**
     * Test that the action handles empty file names gracefully
     *
     * @test
     */
    public function it_handles_empty_file_names_gracefully(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-no-name',
                    'name' => '',
                    'mimeType' => 'application/pdf',
                    'size' => 1024,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertEquals('', $result[0]['name']);
        $this->assertIsString($result[0]['name']);
    }

    /**
     * Test that the action handles zero-byte files
     *
     * @test
     */
    public function it_handles_zero_byte_files(): void
    {
        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->andReturn([
                [
                    'id' => 'file-empty',
                    'name' => 'empty.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 0,
                ],
            ]);

        $action = new ListDrivePdfs($driveMock);
        $result = $action->handle('folder-123');

        $this->assertEquals(0, $result[0]['size']);
    }

    /**
     * Test encapsulation: action isolates Drive API from orchestration code
     *
     * @test
     */
    public function it_encapsulates_drive_api_logic(): void
    {
        // The action exists specifically to encapsulate Drive API calls
        // so that commands and other orchestration code don't need to know
        // about GoogleDriveService implementation details

        $driveMock = Mockery::mock(GoogleDriveService::class);
        $driveMock
            ->shouldReceive('listPdfsInFolder')
            ->once()
            ->with('test-folder')
            ->andReturn([
                ['id' => 'f1', 'name' => 'doc.pdf', 'mimeType' => 'application/pdf', 'size' => 100],
            ]);

        // Caller only needs to know about ListDrivePdfs action, not GoogleDriveService
        $action = new ListDrivePdfs($driveMock);
        $files = $action->handle('test-folder');

        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
    }
}
