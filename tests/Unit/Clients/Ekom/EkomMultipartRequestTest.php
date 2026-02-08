<?php

namespace Tests\Unit\Clients\Ekom;

use App\Clients\Ekom\EkomMultipartRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EkomMultipartRequestTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/ekom_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    // ========================
    // forPodnesak tests
    // ========================

    public function test_for_podnesak_builds_multipart_with_json_and_files(): void
    {
        $file1 = $this->tempDir . '/document1.pdf';
        $file2 = $this->tempDir . '/document2.pdf';
        file_put_contents($file1, 'PDF content 1');
        file_put_contents($file2, 'PDF content 2');

        $payload = [
            'vrstaPostupkaId' => 1,
            'sadrzaj' => [
                ['naziv' => 'document1.pdf', 'vrsta' => 'GLAVNA'],
                ['naziv' => 'document2.pdf', 'vrsta' => 'PRILOG'],
            ],
        ];

        $result = EkomMultipartRequest::forPodnesak($payload, [$file1, $file2]);

        $this->assertIsArray($result);
        $this->assertCount(3, $result); // 1 JSON part + 2 file parts

        // First element is JSON payload
        $this->assertSame('podnesak', $result[0]['name']);
        $this->assertSame('application/json', $result[0]['headers']['Content-Type']);

        // Second and third are files
        $this->assertSame('files', $result[1]['name']);
        $this->assertSame('document1.pdf', $result[1]['filename']);
        $this->assertSame('files', $result[2]['name']);
        $this->assertSame('document2.pdf', $result[2]['filename']);
    }

    public function test_for_podnesak_validates_filename_match(): void
    {
        $file = $this->tempDir . '/actual-file.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'sadrzaj' => [
                ['naziv' => 'different-name.pdf', 'vrsta' => 'GLAVNA'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename mismatch');

        EkomMultipartRequest::forPodnesak($payload, [$file]);
    }

    public function test_for_podnesak_validates_file_count_matches_sadrzaj(): void
    {
        $file = $this->tempDir . '/document.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'sadrzaj' => [
                ['naziv' => 'document.pdf', 'vrsta' => 'GLAVNA'],
                ['naziv' => 'missing.pdf', 'vrsta' => 'PRILOG'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File count mismatch');

        EkomMultipartRequest::forPodnesak($payload, [$file]);
    }

    public function test_for_podnesak_validates_files_exist(): void
    {
        $payload = [
            'sadrzaj' => [
                ['naziv' => 'nonexistent.pdf', 'vrsta' => 'GLAVNA'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        EkomMultipartRequest::forPodnesak($payload, ['/nonexistent/path/nonexistent.pdf']);
    }

    public function test_for_podnesak_json_encodes_payload_correctly(): void
    {
        $file = $this->tempDir . '/test.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'vrstaPostupkaId' => 1,
            'napomena' => 'Test with special chars: a/b & c',
            'sadrzaj' => [
                ['naziv' => 'test.pdf', 'vrsta' => 'GLAVNA'],
            ],
        ];

        $result = EkomMultipartRequest::forPodnesak($payload, [$file]);

        $jsonContent = $result[0]['contents'];
        $decoded = json_decode($jsonContent, true);

        $this->assertSame($payload, $decoded);
        // Verify no escaping of slashes (JSON_UNESCAPED_SLASHES)
        $this->assertStringContainsString('a/b', $jsonContent);
    }

    public function test_for_podnesak_works_without_sadrzaj_array(): void
    {
        // Edge case: no files, no sadrzaj
        $payload = [
            'vrstaPostupkaId' => 1,
        ];

        $result = EkomMultipartRequest::forPodnesak($payload, []);

        $this->assertCount(1, $result); // Just the JSON part
        $this->assertSame('podnesak', $result[0]['name']);
    }

    // ========================
    // forPrilog tests
    // ========================

    public function test_for_prilog_builds_multipart_with_json_and_single_file(): void
    {
        $file = $this->tempDir . '/attachment.pdf';
        file_put_contents($file, 'PDF attachment content');

        $payload = [
            'vrsta' => 'PRILOG',
            'sadrzaj' => [
                'naziv' => 'attachment.pdf',
                'opis' => 'Test attachment',
            ],
        ];

        $result = EkomMultipartRequest::forPrilog($payload, $file);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // 1 JSON part + 1 file part

        // First element is JSON payload
        $this->assertSame('prilog', $result[0]['name']);
        $this->assertSame('application/json', $result[0]['headers']['Content-Type']);

        // Second is the file
        $this->assertSame('file', $result[1]['name']);
        $this->assertSame('attachment.pdf', $result[1]['filename']);
    }

    public function test_for_prilog_validates_filename_match(): void
    {
        $file = $this->tempDir . '/actual-file.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'sadrzaj' => [
                'naziv' => 'different-name.pdf',
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filename mismatch');

        EkomMultipartRequest::forPrilog($payload, $file);
    }

    public function test_for_prilog_validates_file_exists(): void
    {
        $payload = [
            'sadrzaj' => [
                'naziv' => 'nonexistent.pdf',
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        EkomMultipartRequest::forPrilog($payload, '/nonexistent/path/nonexistent.pdf');
    }

    public function test_for_prilog_json_encodes_payload_correctly(): void
    {
        $file = $this->tempDir . '/test.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'vrsta' => 'PRILOG',
            'napomena' => 'Unicode test: Cevapici',
            'sadrzaj' => [
                'naziv' => 'test.pdf',
            ],
        ];

        $result = EkomMultipartRequest::forPrilog($payload, $file);

        $jsonContent = $result[0]['contents'];
        $decoded = json_decode($jsonContent, true);

        $this->assertSame($payload, $decoded);
        // Verify Unicode is not escaped (JSON_UNESCAPED_UNICODE)
        $this->assertStringContainsString('Cevapici', $jsonContent);
    }

    public function test_for_prilog_handles_nested_sadrzaj_naziv(): void
    {
        $file = $this->tempDir . '/nested-test.pdf';
        file_put_contents($file, 'content');

        $payload = [
            'sadrzaj' => [
                'naziv' => 'nested-test.pdf',
                'dodatniPodaci' => ['key' => 'value'],
            ],
        ];

        $result = EkomMultipartRequest::forPrilog($payload, $file);

        $this->assertCount(2, $result);
        $this->assertSame('nested-test.pdf', $result[1]['filename']);
    }
}
