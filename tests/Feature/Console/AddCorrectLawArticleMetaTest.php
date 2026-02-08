<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AddCorrectLawArticleMetaTest extends TestCase
{
    use UsesTestDatabase;

    protected $testMappingPath;

    protected $testLawPath;

    protected $testMetaPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test directories using File facade to ensure they physically exist
        $taggedPath = storage_path('app/tagged');
        $lawPath = storage_path('app/reposss/Clanci2/KazneniZakon');

        if (! File::exists($taggedPath)) {
            File::makeDirectory($taggedPath, 0755, true);
        }

        if (! File::exists($lawPath)) {
            File::makeDirectory($lawPath, 0755, true);
        }

        $this->testMappingPath = storage_path('app/tagged/mappping.json');
        $this->testLawPath = storage_path('app/reposss/Clanci2/KazneniZakon');
        $this->testMetaPath = storage_path('app/tagged/kazneniZakon.metadata.json');
    }

    protected function tearDown(): void
    {
        // Clean up test files and directories
        @unlink($this->testMappingPath);
        @unlink($this->testMetaPath);

        $taggedPath = storage_path('app/tagged');
        $reposPath = storage_path('app/reposss');

        if (File::exists($taggedPath)) {
            File::deleteDirectory($taggedPath);
        }

        if (File::exists($reposPath)) {
            File::deleteDirectory($reposPath);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_processes_law_articles_successfully()
    {
        // Create mapping file
        file_put_contents($this->testMappingPath, json_encode([]));

        // Create metadata file
        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        // Create test PDF files
        file_put_contents($this->testLawPath.'/clanak-1.pdf', 'PDF content');
        file_put_contents($this->testLawPath.'/clanak-2.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->expectsOutput('Processing: '.$this->testLawPath)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_creates_article_metadata_files()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-15.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        // Check that metadata file was created
        $articleMetaPath = storage_path('app/tagged/clanak-15.metadata.json');
        $this->assertFileExists($articleMetaPath);

        // Verify metadata content
        $articleMeta = json_decode(file_get_contents($articleMetaPath), true);
        $this->assertEquals('clanak-15.pdf', $articleMeta['file_name']);
        $this->assertEquals('KZ', $articleMeta['law'][0]['law_code']);
        $this->assertEquals('15', $articleMeta['law'][0]['citations'][0]['clanak']);

        @unlink($articleMetaPath);
    }

    /** @test */
    public function it_updates_mapping_file()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-10.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        // Check mapping was updated
        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertNotEmpty($mapping);
        $this->assertEquals('clanak-10.pdf', $mapping[0]['file_name']);
        $this->assertStringContainsString('clanak-10.pdf', $mapping[0]['file_path']);
    }

    /** @test */
    public function it_handles_multiple_pdf_files()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        // Create multiple PDFs
        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($this->testLawPath."/clanak-{$i}.pdf", 'PDF content');
        }

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        // Verify all were processed
        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertCount(5, $mapping);
    }

    /** @test */
    public function it_filters_non_pdf_files()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        // Create mix of PDF and non-PDF files
        file_put_contents($this->testLawPath.'/clanak-1.pdf', 'PDF content');
        file_put_contents($this->testLawPath.'/readme.txt', 'Text content');
        file_put_contents($this->testLawPath.'/clanak-2.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        // Only PDFs should be processed
        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertCount(2, $mapping);
    }

    /** @test */
    public function it_extracts_article_numbers_correctly()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-123.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $articleMetaPath = storage_path('app/tagged/clanak-123.metadata.json');
        $articleMeta = json_decode(file_get_contents($articleMetaPath), true);
        $this->assertEquals('123', $articleMeta['law'][0]['citations'][0]['clanak']);

        @unlink($articleMetaPath);
    }

    /** @test */
    public function it_preserves_law_metadata_structure()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                    'additional_field' => 'test_value',
                ],
            ],
            'other_data' => 'preserved',
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-5.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $articleMetaPath = storage_path('app/tagged/clanak-5.metadata.json');
        $articleMeta = json_decode(file_get_contents($articleMetaPath), true);

        // Verify original metadata fields are preserved
        $this->assertEquals('preserved', $articleMeta['other_data']);

        @unlink($articleMetaPath);
    }

    /** @test */
    public function it_creates_citations_array_structure()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-7.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $articleMetaPath = storage_path('app/tagged/clanak-7.metadata.json');
        $articleMeta = json_decode(file_get_contents($articleMetaPath), true);

        $citation = $articleMeta['law'][0]['citations'][0];
        $this->assertEquals('7', $citation['clanak']);
        $this->assertIsArray($citation['stavci']);
        $this->assertIsArray($citation['tocke']);
        $this->assertEmpty($citation['stavci']);
        $this->assertEmpty($citation['tocke']);

        @unlink($articleMetaPath);
    }

    /** @test */
    public function it_handles_empty_directory()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        // No PDF files created

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertEmpty($mapping);
    }

    /** @test */
    public function it_sets_file_id_and_response_to_null()
    {
        file_put_contents($this->testMappingPath, json_encode([]));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-1.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertNull($mapping[0]['file_id']);
        $this->assertNull($mapping[0]['file_response']);
    }

    /** @test */
    public function it_appends_to_existing_mapping()
    {
        // Create existing mapping
        $existingMapping = [
            [
                'file_path' => '/existing/file.pdf',
                'file_name' => 'existing.pdf',
            ],
        ];
        file_put_contents($this->testMappingPath, json_encode($existingMapping));

        $metadata = [
            'law' => [
                [
                    'law_code' => 'KZ',
                    'law_code_alias' => 'Kazneni zakon',
                ],
            ],
        ];
        file_put_contents($this->testMetaPath, json_encode($metadata));

        file_put_contents($this->testLawPath.'/clanak-1.pdf', 'PDF content');

        $this->artisan('app:add-correct-law-article-meta')
            ->assertExitCode(0);

        $mapping = json_decode(file_get_contents($this->testMappingPath), true);
        $this->assertCount(2, $mapping);
        $this->assertEquals('existing.pdf', $mapping[0]['file_name']);
        $this->assertEquals('clanak-1.pdf', $mapping[1]['file_name']);
    }
}
