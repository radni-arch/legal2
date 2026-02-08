<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\EkomService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EkomTestSubmitCommandTest extends TestCase
{
    private string $fixturesPath;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = base_path('tests/fixtures/ekom');
        $this->tempDir = sys_get_temp_dir() . '/ekom-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_requires_token_file_option(): void
    {
        $this->artisan('ekom:test-submit')
            ->assertExitCode(1)
            ->expectsOutputToContain('--token-file');
    }

    /** @test */
    public function it_requires_json_file_option(): void
    {
        // Create a temp token file
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('--json-file');
    }

    /** @test */
    public function it_validates_token_file_exists(): void
    {
        $this->artisan('ekom:test-submit', [
            '--token-file' => '/nonexistent/token.txt',
            '--json-file' => $this->fixturesPath . '/prilog.json',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Token file not found');
    }

    /** @test */
    public function it_validates_json_file_exists(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => '/nonexistent/payload.json',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('JSON file not found');
    }

    /** @test */
    public function it_validates_json_file_content(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $invalidJson = $this->tempDir . '/invalid.json';
        file_put_contents($invalidJson, 'not valid json {');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $invalidJson,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Invalid JSON');
    }

    /** @test */
    public function it_validates_file_attachments_exist(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
            '--files' => '/nonexistent/file.pdf',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('File not found');
    }

    /** @test */
    public function it_shows_dry_run_output_without_making_request(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token-12345');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('predmetOznaka');
    }

    /** @test */
    public function it_saves_response_to_file_when_option_provided(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 12345,
                'status' => 'CREATED',
            ], 201),
        ]);

        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $responseFile = $this->tempDir . '/response.json';

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
            '--save-response' => $responseFile,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($responseFile);
        $savedResponse = json_decode(file_get_contents($responseFile), true);
        $this->assertEquals(12345, $savedResponse['id']);
    }

    /** @test */
    public function it_displays_curl_equivalent_command(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
            '--dry-run' => true,
            '--show-curl' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('curl');
    }

    /** @test */
    public function it_accepts_multiple_file_attachments(): void
    {
        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $file1 = $this->tempDir . '/file1.pdf';
        $file2 = $this->tempDir . '/file2.pdf';
        file_put_contents($file1, '%PDF-1.4 test content 1');
        file_put_contents($file2, '%PDF-1.4 test content 2');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
            '--files' => "$file1,$file2",
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('file1.pdf')
            ->expectsOutputToContain('file2.pdf');
    }

    /** @test */
    public function it_uses_correct_api_endpoint(): void
    {
        Http::fake([
            '*/api/ekom/v1/podnesci' => Http::response(['id' => 1], 201),
        ]);

        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/ekom/v1/podnesci');
        });
    }

    /** @test */
    public function it_sets_authorization_header_from_token_file(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 1], 201),
        ]);

        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'my-secret-token-value');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-token-value');
        });
    }

    /** @test */
    public function it_handles_api_error_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => 'Validation failed',
                'details' => ['sudOznaka' => 'Invalid court'],
            ], 400),
        ]);

        $tokenFile = $this->tempDir . '/token.txt';
        file_put_contents($tokenFile, 'test-token');

        $this->artisan('ekom:test-submit', [
            '--token-file' => $tokenFile,
            '--json-file' => $this->fixturesPath . '/podnesak-postojeci_predmet-bez_pristojbe.json',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('Error');
    }
}
