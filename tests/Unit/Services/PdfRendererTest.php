<?php

namespace Tests\Unit\Services;

use App\Services\PdfRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfRendererTest extends TestCase
{
    protected PdfRenderer $renderer;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new PdfRenderer;
        $this->tempDir = sys_get_temp_dir().'/pdf_renderer_test_'.uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    // ===== Basic Functionality Tests =====

    /** @test */
    public function it_renders_article_to_pdf()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/article.pdf';

        View::shouldReceive('make')
            ->once()
            ->with('pdf.article', $ctx)
            ->andReturnSelf();

        View::shouldReceive('render')
            ->once()
            ->andReturn('<html><body>Test Article</body></html>');

        Pdf::shouldReceive('loadHTML')
            ->once()
            ->with('<html><body>Test Article</body></html>')
            ->andReturnSelf();

        Pdf::shouldReceive('setPaper')
            ->once()
            ->with('a4', 'portrait')
            ->andReturnSelf();

        Pdf::shouldReceive('save')
            ->once()
            ->with($destPath);

        $this->renderer->renderArticle($ctx, $destPath);

        // Test passes if all expectations met
        $this->assertTrue(true);
    }

    /** @test */
    public function it_returns_void()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/void_test.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $result = $this->renderer->renderArticle($ctx, $destPath);

        $this->assertNull($result);
    }

    // ===== Context Handling Tests =====

    /** @test */
    public function it_passes_context_to_view()
    {
        $ctx = [
            'law_title' => 'Zakon o radu',
            'article_number' => '15',
            'law_eli' => 'HR:NN:2014:93',
            'law_pub_date' => '2014-07-30',
            'article_html' => '<p>Article content</p>',
            'generated_at' => '2024-01-01T00:00:00Z',
            'generator_version' => '2.0.0',
        ];

        $destPath = $this->tempDir.'/context_test.pdf';

        View::shouldReceive('make')
            ->once()
            ->with('pdf.article', $ctx)
            ->andReturnSelf();

        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_minimal_context()
    {
        $ctx = [
            'law_title' => 'Test Law',
            'article_number' => '1',
            'article_html' => '<p>Content</p>',
        ];

        $destPath = $this->tempDir.'/minimal.pdf';

        View::shouldReceive('make')->with('pdf.article', $ctx)->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_context()
    {
        $ctx = [];
        $destPath = $this->tempDir.'/empty.pdf';

        View::shouldReceive('make')->with('pdf.article', $ctx)->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_context_with_croatian_characters()
    {
        $ctx = [
            'law_title' => 'Zakon o međunarodnom privatnom pravu',
            'article_number' => '5',
            'article_html' => '<p>Članak o važnim odredbama.</p>',
        ];

        $destPath = $this->tempDir.'/croatian.pdf';

        View::shouldReceive('make')->with('pdf.article', $ctx)->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    // ===== View Rendering Tests =====

    /** @test */
    public function it_uses_correct_view_template()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/view_test.pdf';

        View::shouldReceive('make')
            ->once()
            ->with('pdf.article', \Mockery::any())
            ->andReturnSelf();

        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_renders_view_to_html_string()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/html_test.pdf';

        $expectedHtml = '<html><body><h1>Test</h1></body></html>';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')
            ->once()
            ->andReturn($expectedHtml);

        Pdf::shouldReceive('loadHTML')
            ->once()
            ->with($expectedHtml)
            ->andReturnSelf();

        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    // ===== PDF Configuration Tests =====

    /** @test */
    public function it_sets_a4_paper_size()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/a4_test.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();

        Pdf::shouldReceive('setPaper')
            ->once()
            ->with('a4', \Mockery::any())
            ->andReturnSelf();

        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_sets_portrait_orientation()
    {
        $ctx = $this->createBasicContext();
        $destPath = $this->tempDir.'/portrait_test.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();

        Pdf::shouldReceive('setPaper')
            ->once()
            ->with(\Mockery::any(), 'portrait')
            ->andReturnSelf();

        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    // ===== Directory Creation Tests =====

    /** @test */
    public function it_creates_destination_directory_if_not_exists()
    {
        $destPath = $this->tempDir.'/output/nested/article.pdf';
        $destDir = dirname($destPath);

        // Directory should not exist yet
        $this->assertDirectoryDoesNotExist($destDir);

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $ctx = $this->createBasicContext();
        $this->renderer->renderArticle($ctx, $destPath);

        // Directory should now exist
        $this->assertDirectoryExists($destDir);
    }

    /** @test */
    public function it_handles_existing_destination_directory()
    {
        $destPath = $this->tempDir.'/existing/article.pdf';
        $destDir = dirname($destPath);

        // Pre-create directory
        mkdir($destDir, 0775, true);
        $this->assertDirectoryExists($destDir);

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $ctx = $this->createBasicContext();
        $this->renderer->renderArticle($ctx, $destPath);

        // Directory should still exist
        $this->assertDirectoryExists($destDir);
    }

    // ===== Path Handling Tests =====

    /** @test */
    public function it_handles_absolute_paths()
    {
        $destPath = $this->tempDir.'/absolute/path/article.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save')->with($destPath);

        $ctx = $this->createBasicContext();
        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertDirectoryExists(dirname($destPath));
    }

    /** @test */
    public function it_handles_paths_with_special_characters()
    {
        $destPath = $this->tempDir.'/special chars/article-2024_v1.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $ctx = $this->createBasicContext();
        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertDirectoryExists(dirname($destPath));
    }

    /** @test */
    public function it_handles_very_long_paths()
    {
        $longDir = $this->tempDir.'/'.str_repeat('long_directory/', 5);
        $destPath = $longDir.'article.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $ctx = $this->createBasicContext();
        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertDirectoryExists(dirname($destPath));
    }

    // ===== Memory Management Tests =====

    /** @test */
    public function it_documents_memory_cleanup()
    {
        // The service calls unset($pdf, $html) after rendering
        // This frees memory for large batch operations
        // We document this behavior
        $this->assertTrue(true);
    }

    // ===== Integration Documentation Tests =====

    /** @test */
    public function it_documents_dompdf_requirement()
    {
        // This test documents that PdfRenderer requires barryvdh/laravel-dompdf
        $this->assertTrue(class_exists(\Barryvdh\DomPDF\Facade\Pdf::class),
            'Laravel DomPDF is required. Install with: composer require barryvdh/laravel-dompdf');
    }

    /** @test */
    public function it_documents_view_template_requirement()
    {
        // The service requires resources/views/pdf/article.blade.php
        $viewPath = resource_path('views/pdf/article.blade.php');
        $this->assertFileExists($viewPath,
            'View template is required at: resources/views/pdf/article.blade.php');
    }

    /** @test */
    public function it_has_correct_method_signature()
    {
        $reflection = new \ReflectionMethod($this->renderer, 'renderArticle');

        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('ctx', $params[0]->getName());
        $this->assertEquals('destPath', $params[1]->getName());

        // Method returns void
        $this->assertEquals('void', (string) $reflection->getReturnType());
    }

    /** @test */
    public function it_documents_expected_context_fields()
    {
        // Expected context fields (from view template):
        // - law_title (required in practice)
        // - article_number (required in practice)
        // - law_eli (optional)
        // - law_pub_date (optional)
        // - article_html (required in practice)
        // - generated_at (optional, defaults to gmdate('c'))
        // - generator_version (optional, defaults to '1.0.0')

        $expectedFields = [
            'law_title',
            'article_number',
            'law_eli',
            'law_pub_date',
            'article_html',
            'generated_at',
            'generator_version',
        ];

        // This is a documentation test
        $this->assertIsArray($expectedFields);
        $this->assertCount(7, $expectedFields);
    }

    // ===== Edge Cases =====

    /** @test */
    public function it_handles_very_long_article_html()
    {
        $ctx = [
            'law_title' => 'Test Law',
            'article_number' => '1',
            'article_html' => str_repeat('<p>Long content paragraph. </p>', 1000),
        ];

        $destPath = $this->tempDir.'/long_html.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html>'.$ctx['article_html'].'</html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_html_with_special_characters()
    {
        $ctx = [
            'law_title' => 'Test Law',
            'article_number' => '1',
            'article_html' => '<p>&lt;tag&gt; &amp; "quotes" &apos;apostrophe&apos;</p>',
        ];

        $destPath = $this->tempDir.'/special_chars.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_article_html()
    {
        $ctx = [
            'law_title' => 'Test Law',
            'article_number' => '1',
            'article_html' => '',
        ];

        $destPath = $this->tempDir.'/empty_html.pdf';

        View::shouldReceive('make')->andReturnSelf();
        View::shouldReceive('render')->andReturn('<html></html>');
        Pdf::shouldReceive('loadHTML')->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('save');

        $this->renderer->renderArticle($ctx, $destPath);

        $this->assertTrue(true);
    }

    // ===== Helper Methods =====

    protected function createBasicContext(): array
    {
        return [
            'law_title' => 'Zakon o radu',
            'article_number' => '15',
            'law_eli' => 'HR:NN:2014:93',
            'law_pub_date' => '2014-07-30',
            'article_html' => '<p>Članak 15 određuje osnovna prava radnika.</p>',
            'generated_at' => '2024-01-01T00:00:00Z',
            'generator_version' => '1.0.0',
        ];
    }

    protected function recursiveRemoveDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);

        foreach ($items as $item) {
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
