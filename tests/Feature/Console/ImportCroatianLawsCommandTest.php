<?php

namespace Tests\Feature\Console;

use App\Services\LawFetcher;
use App\Services\LawParser;
use App\Services\MetadataBuilder;
use App\Services\NnApiClient;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ImportCroatianLawsCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $nnApiMock;

    protected $fetcherMock;

    protected $parserMock;

    protected $pdfMock;

    protected $metaMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nnApiMock = Mockery::mock(NnApiClient::class);
        $this->fetcherMock = Mockery::mock(LawFetcher::class);
        $this->parserMock = Mockery::mock(LawParser::class);
        $this->pdfMock = Mockery::mock(PdfRenderer::class);
        $this->metaMock = Mockery::mock(MetadataBuilder::class);

        $this->app->instance(NnApiClient::class, $this->nnApiMock);
        $this->app->instance(LawFetcher::class, $this->fetcherMock);
        $this->app->instance(LawParser::class, $this->parserMock);
        $this->app->instance(PdfRenderer::class, $this->pdfMock);
        $this->app->instance(MetadataBuilder::class, $this->metaMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_imports_croatian_laws_successfully()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Zakon o kaznenom postupku',
                'year' => 2024,
                'edition' => 152,
                'act' => '08',
                'date_publication' => '2024-01-15',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:zkp:2024',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->with(null)
            ->once()
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => 1, 'content' => '<p>Članak 1 sadržaj</p>'],
                ['number' => 2, 'content' => '<p>Članak 2 sadržaj</p>'],
            ]);

        $this->pdfMock
            ->shouldReceive('render')
            ->twice()
            ->andReturn('PDF content');

        $this->metaMock
            ->shouldReceive('build')
            ->twice()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest')
            ->expectsOutputToContain('Zakon o kaznenom postupku')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_filters_by_year_with_since_option()
    {
        Storage::fake('local');

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->with(2023)
            ->once()
            ->andReturn([]);

        $this->artisan('hrlaws:ingest', ['--since' => 2023])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_stops_after_first_act_with_only_latest()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'First Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act1.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:act1',
            ],
            [
                'title' => 'Second Act',
                'year' => 2024,
                'edition' => 2,
                'act' => '02',
                'date_publication' => '2024-01-02',
                'html_url' => 'http://example.com/act2.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:act2',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->once() // Only first act should be processed
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--only-latest' => 1])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_limits_number_of_acts_processed()
    {
        Storage::fake('local');

        $acts = [];
        for ($i = 1; $i <= 5; $i++) {
            $acts[] = [
                'title' => "Act $i",
                'year' => 2024,
                'edition' => $i,
                'act' => str_pad($i, 2, '0', STR_PAD_LEFT),
                'date_publication' => "2024-01-0$i",
                'html_url' => "http://example.com/act$i.html",
                'is_consolidated' => true,
                'eli_resource' => "eli:hr:act$i",
            ];
        }

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->times(2) // Only 2 acts should be processed
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->times(2)
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->times(2)
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--limit' => 2])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_uses_custom_output_directory()
    {
        Storage::fake('local');

        $customDir = Storage::path('custom-laws');

        $acts = [
            [
                'title' => 'Test Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:test',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--out' => $customDir])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_filters_only_consolidated_texts()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Consolidated Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/consolidated.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:consolidated',
            ],
            [
                'title' => 'Non-Consolidated Act',
                'year' => 2024,
                'edition' => 2,
                'act' => '02',
                'date_publication' => '2024-01-02',
                'html_url' => 'http://example.com/non-consolidated.html',
                'is_consolidated' => false,
                'eli_resource' => 'eli:hr:non-consolidated',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->once() // Only consolidated should be processed
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--only-consolidated' => 1])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_acts_without_html_url()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Act Without HTML',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => null, // No HTML URL
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:no-html',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldNotReceive('splitIntoArticles');

        $this->artisan('hrlaws:ingest')
            ->expectsOutputToContain('No HTML/printhtml')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_failed_html_download()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Act With Bad URL',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://invalid-url-that-will-fail.test',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:bad-url',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldNotReceive('splitIntoArticles');

        $this->artisan('hrlaws:ingest')
            ->expectsOutputToContain('Failed to download HTML')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_splits_acts_into_individual_articles()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Multi-Article Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:multi',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->once()
            ->andReturn([
                ['number' => 1, 'content' => '<p>Article 1</p>'],
                ['number' => 2, 'content' => '<p>Article 2</p>'],
                ['number' => 3, 'content' => '<p>Article 3</p>'],
            ]);

        $this->pdfMock
            ->shouldReceive('render')
            ->times(3) // One per article
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->times(3)
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_generates_pdfs_for_articles()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Test Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:test',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('array'))
            ->andReturn('PDF content');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--mode' => 'render'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_sidecar_metadata_files()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Test Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:test',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['title' => 'Article 1', 'eli' => 'eli:hr:test:article:1']);

        $this->artisan('hrlaws:ingest', ['--sidecar' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_parses_extra_attributes()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Test Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:test',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--attrs' => 'jurisdiction=criminal,court=VSRH'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_respects_throttle_setting()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Test Act',
                'year' => 2024,
                'edition' => 1,
                'act' => '01',
                'date_publication' => '2024-01-01',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:test',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest', ['--throttle-ms' => 500])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_act_information_during_processing()
    {
        Storage::fake('local');

        $acts = [
            [
                'title' => 'Zakon o kaznenom postupku',
                'year' => 2024,
                'edition' => 152,
                'act' => '08',
                'date_publication' => '2024-01-15',
                'html_url' => 'http://example.com/act.html',
                'is_consolidated' => true,
                'eli_resource' => 'eli:hr:zkp',
            ],
        ];

        $this->fetcherMock
            ->shouldReceive('latestConsolidations')
            ->andReturn($acts);

        $this->parserMock
            ->shouldReceive('splitIntoArticles')
            ->andReturn([['number' => 1, 'content' => '<p>Content</p>']]);

        $this->pdfMock
            ->shouldReceive('render')
            ->once()
            ->andReturn('PDF');

        $this->metaMock
            ->shouldReceive('build')
            ->once()
            ->andReturn(['metadata' => 'data']);

        $this->artisan('hrlaws:ingest')
            ->expectsOutputToContain('Zakon o kaznenom postupku')
            ->expectsOutputToContain('[2024/152/08]')
            ->expectsOutputToContain('2024-01-15')
            ->assertExitCode(0);
    }
}
