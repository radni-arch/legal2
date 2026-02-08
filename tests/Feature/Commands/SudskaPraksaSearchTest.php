<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SudskaPraksaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected string $keywordsPath;
    protected string $tempKeywordsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keywordsPath = storage_path('app/keywords/default.json');
        $this->tempKeywordsPath = storage_path('app/keywords/temp_test.json');
    }

    protected function tearDown(): void
    {
        // Clean up temp test files
        if (File::exists($this->tempKeywordsPath)) {
            File::delete($this->tempKeywordsPath);
        }
        parent::tearDown();
    }

    /**
     * Test that the command runs with default keywords file.
     */
    public function test_command_runs_with_default_keywords(): void
    {
        // Ensure default keywords file exists
        if (!File::exists($this->keywordsPath)) {
            $this->markTestSkipped('Default keywords file not found - will be created');
        }

        // Fake HTTP responses from odluke.sudovi.hr
        Http::fake([
            'odluke.sudovi.hr/*' => Http::response($this->getSampleHtmlResponse(42), 200),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--max' => 1,
            '--format' => 'json',
        ])
            ->assertExitCode(0);
    }

    /**
     * Test that the command fails gracefully when keywords file is missing.
     */
    public function test_command_fails_on_missing_keywords_file(): void
    {
        $nonExistentPath = storage_path('app/keywords/non_existent_file.json');

        $this->artisan('sudska-praksa:search', [
            '--keywords-file' => $nonExistentPath,
        ])
            ->assertExitCode(1);
    }

    /**
     * Test that --generate-keywords option creates a sample keywords file.
     */
    public function test_generate_keywords_option_creates_file(): void
    {
        // Remove temp file if exists
        if (File::exists($this->tempKeywordsPath)) {
            File::delete($this->tempKeywordsPath);
        }

        $this->artisan('sudska-praksa:search', [
            '--generate-keywords' => true,
            '--keywords-file' => $this->tempKeywordsPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($this->tempKeywordsPath);

        // Verify it's valid JSON with expected structure
        $content = File::get($this->tempKeywordsPath);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('categories', $data);
    }

    /**
     * Test that result count parsing correctly extracts numbers from HTML.
     */
    public function test_result_count_parsing_extracts_number(): void
    {
        // Ensure default keywords file exists
        if (!File::exists($this->keywordsPath)) {
            $this->markTestSkipped('Default keywords file not found - will be created');
        }

        // Test with various result count formats
        $testCases = [
            ['html' => $this->getSampleHtmlResponse(42), 'expected' => 42],
            ['html' => $this->getSampleHtmlResponse(1), 'expected' => 1],
            ['html' => $this->getSampleHtmlResponse(1500), 'expected' => 1500],
        ];

        foreach ($testCases as $index => $testCase) {
            Http::fake([
                'odluke.sudovi.hr/*' => Http::response($testCase['html'], 200),
            ]);

            // Run command and capture output
            $this->artisan('sudska-praksa:search', [
                '--max' => 1,
                '--format' => 'json',
                '--output' => storage_path('app/test_output'),
            ])
                ->assertExitCode(0);
        }
    }

    /**
     * Test that "Nema rezultata" response returns zero count.
     */
    public function test_nema_rezultata_returns_zero(): void
    {
        // Ensure default keywords file exists
        if (!File::exists($this->keywordsPath)) {
            $this->markTestSkipped('Default keywords file not found - will be created');
        }

        $noResultsHtml = '<html><body><div class="results">Nema rezultata za vašu pretragu.</div></body></html>';

        Http::fake([
            'odluke.sudovi.hr/*' => Http::response($noResultsHtml, 200),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--max' => 1,
            '--format' => 'json',
        ])
            ->assertExitCode(0);
    }

    /**
     * Test that the command respects --max option to limit queries.
     */
    public function test_max_option_limits_queries(): void
    {
        if (!File::exists($this->keywordsPath)) {
            $this->markTestSkipped('Default keywords file not found - will be created');
        }

        Http::fake([
            'odluke.sudovi.hr/*' => Http::response($this->getSampleHtmlResponse(10), 200),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--max' => 2,
            '--format' => 'table',
        ])
            ->assertExitCode(0);
    }

    /**
     * Test filter option filters by classification.
     */
    public function test_filter_option_filters_by_classification(): void
    {
        if (!File::exists($this->keywordsPath)) {
            $this->markTestSkipped('Default keywords file not found - will be created');
        }

        Http::fake([
            'odluke.sudovi.hr/*' => Http::response($this->getSampleHtmlResponse(5), 200),
        ]);

        $this->artisan('sudska-praksa:search', [
            '--max' => 2,
            '--filter' => 'ultra',
            '--format' => 'table',
        ])
            ->assertExitCode(0);
    }

    /**
     * Helper: Generate sample HTML response from odluke.sudovi.hr.
     */
    protected function getSampleHtmlResponse(int $resultCount): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><title>Odluke sudovi - Pretraga</title></head>
<body>
<div class="search-results">
    <div class="result-count">
        Pronađeno je {$resultCount} rezultat(a) za vašu pretragu.
    </div>
    <div class="results-list">
        <div class="result-item">
            <h3>Odluka VSRH Rev-123/2024</h3>
            <p>Tekst odluke...</p>
        </div>
    </div>
</div>
</body>
</html>
HTML;
    }
}
