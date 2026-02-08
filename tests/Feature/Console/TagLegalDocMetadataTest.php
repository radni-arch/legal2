<?php

namespace Tests\Feature\Console;

use App\Models\VectorDocument;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the ai:tag command (TagLegalDocMetadata).
 *
 * Verifies that the command correctly:
 * - Saves tagged metadata to the vector_documents table
 * - Uses CatalogService for attribute compaction
 * - Respects the --vs option for vector store ID
 * - Maintains backward compatibility with mapping.json
 */
class TagLegalDocMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected string $testFilePath;
    protected string $outDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test file
        $this->testFilePath = storage_path('app/test-document.pdf');
        file_put_contents($this->testFilePath, 'Test PDF content');

        $this->outDir = storage_path('app/tagged-test');
        if (!is_dir($this->outDir)) {
            mkdir($this->outDir, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }

        // Cleanup output directory
        if (is_dir($this->outDir)) {
            $files = glob($this->outDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->outDir);
        }

        parent::tearDown();
    }

    /**
     * Test that the ai:tag command saves tagged document to vector_documents table.
     */
    public function test_tag_command_saves_to_vector_documents_table(): void
    {
        // Mock OpenAI API responses
        Http::fake([
            '*/v1/files' => Http::response([
                'id' => 'file_test123abc',
                'object' => 'file',
            ], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'function_call',
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => 'Pp-1234/2026',
                            'related_cases' => [],
                            'vrsta' => 'naredba_za_pretragu',
                            'artifact' => 'mobitel',
                            'datum' => '2026-01-15',
                            'lokacija' => 'Zagreb',
                            'law' => [],
                            'kategorije_povrede' => [],
                            'ključne_riječi' => ['pretres', 'mobitel'],
                            'izvor' => 'službeni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.95,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $vsId = 'vs_test_store_id';

        // Run the command
        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--vs' => $vsId,
        ])->assertSuccessful();

        // Assert document was saved to database
        $this->assertDatabaseHas('vector_documents', [
            'file_name' => 'test-document.pdf',
            'vector_store_id' => $vsId,
            'status' => VectorDocument::STATUS_TAGGED,
            'case_id' => 'Pp-1234/2026',
        ]);

        // Verify the document has correct metadata
        $doc = VectorDocument::where('file_name', 'test-document.pdf')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->metadata);
        $this->assertEquals('Pp-1234/2026', $doc->metadata['case_id']);
        $this->assertEquals('naredba_za_pretragu', $doc->metadata['vrsta']);
        $this->assertNotNull($doc->tagged_at);
        $this->assertEquals(0.95, $doc->confidence);
    }

    /**
     * Test that the command uses CatalogService for attribute compaction.
     */
    public function test_tag_command_uses_catalog_service_for_attributes(): void
    {
        Http::fake([
            '*/v1/files' => Http::response(['id' => 'file_abc'], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'function_call',
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => 'Kir-999/2026',
                            'related_cases' => ['Pp-111/2026'],
                            'vrsta' => 'presuda',
                            'artifact' => 'none',
                            'datum' => '2026-02-01',
                            'lokacija' => 'Split',
                            'law' => [
                                [
                                    'law_code' => 'Zakon o kaznenom postupku',
                                    'law_code_alias' => ['ZKP'],
                                    'citations' => [
                                        ['clanak' => '240', 'stavci' => ['1'], 'tocke' => []],
                                    ],
                                    'verzija_od' => null,
                                    'verzija_do' => null,
                                ],
                            ],
                            'kategorije_povrede' => ['Formalni elementi'],
                            'ključne_riječi' => ['presuda', 'kazneni postupak'],
                            'izvor' => 'službeni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.88,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--vs' => 'vs_compact_test',
        ])->assertSuccessful();

        $doc = VectorDocument::where('file_name', 'test-document.pdf')->first();

        // Verify attributes were compacted via CatalogService
        $this->assertNotNull($doc->attributes);
        $this->assertIsArray($doc->attributes);

        // Check that the compact attributes contain expected fields
        $this->assertArrayHasKey('case_id', $doc->attributes);
        $this->assertEquals('Kir-999/2026', $doc->attributes['case_id']);
    }

    /**
     * Test that the --vs option sets the vector store ID.
     */
    public function test_tag_command_respects_vs_option(): void
    {
        Http::fake([
            '*/v1/files' => Http::response(['id' => 'file_xyz'], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => null,
                            'related_cases' => [],
                            'vrsta' => 'drugo',
                            'artifact' => 'none',
                            'datum' => null,
                            'lokacija' => null,
                            'law' => [],
                            'kategorije_povrede' => [],
                            'ključne_riječi' => [],
                            'izvor' => 'interni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.5,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $customVsId = 'vs_custom_store_123';

        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--vs' => $customVsId,
        ])->assertSuccessful();

        $this->assertDatabaseHas('vector_documents', [
            'file_name' => 'test-document.pdf',
            'vector_store_id' => $customVsId,
        ]);
    }

    /**
     * Test that mapping.json is still written for backward compatibility.
     */
    public function test_tag_command_maintains_mapping_json_compatibility(): void
    {
        Http::fake([
            '*/v1/files' => Http::response(['id' => 'file_compat'], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => 'Test-123',
                            'related_cases' => [],
                            'vrsta' => 'drugo',
                            'artifact' => 'none',
                            'datum' => null,
                            'lokacija' => null,
                            'law' => [],
                            'kategorije_povrede' => [],
                            'ključne_riječi' => [],
                            'izvor' => 'interni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.7,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
        ])->assertSuccessful();

        // Mapping file should still be created
        $mappingFile = $this->outDir . '/mappping.json';
        $this->assertFileExists($mappingFile);

        $mapping = json_decode(file_get_contents($mappingFile), true);
        $this->assertIsArray($mapping);
        $this->assertNotEmpty($mapping);
    }

    /**
     * Test that dry run does not save to database.
     */
    public function test_dry_run_does_not_save_to_database(): void
    {
        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--dry' => true,
            '--vs' => 'vs_dry_test',
        ])->assertSuccessful();

        // No HTTP calls should be made in dry run
        Http::assertNothingSent();

        // Database should not have the document
        $this->assertDatabaseMissing('vector_documents', [
            'file_name' => 'test-document.pdf',
            'vector_store_id' => 'vs_dry_test',
        ]);
    }

    /**
     * Test that the openai_file_id is saved from the upload response.
     */
    public function test_tag_command_saves_openai_file_id(): void
    {
        $expectedFileId = 'file_saved_id_abc123';

        Http::fake([
            '*/v1/files' => Http::response(['id' => $expectedFileId], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => null,
                            'related_cases' => [],
                            'vrsta' => 'drugo',
                            'artifact' => 'none',
                            'datum' => null,
                            'lokacija' => null,
                            'law' => [],
                            'kategorije_povrede' => [],
                            'ključne_riječi' => [],
                            'izvor' => 'interni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.6,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--vs' => 'vs_fileid_test',
        ])->assertSuccessful();

        $this->assertDatabaseHas('vector_documents', [
            'file_name' => 'test-document.pdf',
            'openai_file_id' => $expectedFileId,
        ]);
    }

    /**
     * Test that tagger_model is saved from the --model option.
     */
    public function test_tag_command_saves_tagger_model(): void
    {
        Http::fake([
            '*/v1/files' => Http::response(['id' => 'file_model_test'], 200),
            '*/v1/responses' => Http::response([
                'output' => [
                    [
                        'name' => 'tag_file_metadata',
                        'arguments' => json_encode([
                            'file_name' => 'test-document.pdf',
                            'jurisdikcija' => 'HR',
                            'case_id' => null,
                            'related_cases' => [],
                            'vrsta' => 'drugo',
                            'artifact' => 'none',
                            'datum' => null,
                            'lokacija' => null,
                            'law' => [],
                            'kategorije_povrede' => [],
                            'ključne_riječi' => [],
                            'izvor' => 'interni',
                            'store_hint' => null,
                            'anchors' => [],
                            'confidence' => 0.75,
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $modelName = 'gpt-4o-custom';

        $this->artisan('ai:tag', [
            'file' => [$this->testFilePath],
            '--out' => $this->outDir,
            '--model' => $modelName,
            '--vs' => 'vs_model_test',
        ])->assertSuccessful();

        $this->assertDatabaseHas('vector_documents', [
            'file_name' => 'test-document.pdf',
            'tagger_model' => $modelName,
        ]);
    }
}
