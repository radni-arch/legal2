<?php

namespace Tests\Evaluation;

use App\Services\CitationProvenanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * @group evaluation
 * @group grounding
 *
 * Evaluation-only tests for grounding / “no fake laws” enforcement.
 *
 * Run with:
 *   EVAL_TESTS=1 php artisan test --group=evaluation
 */
class CitationProvenanceEvaluationTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! getenv('EVAL_TESTS')) {
            $this->markTestSkipped('Evaluation tests are disabled. Set EVAL_TESTS=1 to enable.');
        }

        if (! Schema::hasTable('laws')) {
            $this->markTestSkipped('laws table not present.');
        }

        if (! Schema::hasTable('citation_provenances')) {
            $this->markTestSkipped('citation_provenances table not present.');
        }
    }

    /** @test */
    public function it_verifies_zkp_citation_against_local_database_without_http(): void
    {
        // Arrange: verifyAgainstDatabase() looks up laws by title LIKE %law_full_name%
        DB::table('laws')->insert([
            'id' => 'law-zkp-test',
            'doc_id' => 'zkp-doc',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'HR',
            'country' => 'HR',
            'language' => 'hr',
            'content' => 'Test content',
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_norm' => null,
            'content_hash' => hash('sha256', 'zkp'),
            'token_count' => 10,
            'embedding_vector' => json_encode(array_fill(0, 1536, 0.0)),
            'metadata' => json_encode(['article_number' => null]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = app(CitationProvenanceService::class);

        // Act: disable API checks (deterministic; service checks DB first anyway)
        $result = $svc->verifyCitation('ZKP čl. 222', ['check_api' => false]);

        // Assert
        $this->assertTrue($result['verified']);
        $this->assertEquals('verified_database', $result['status']);
        $this->assertEquals('database', $result['source']);

        $this->assertDatabaseHas('citation_provenances', [
            'verification_status' => 'verified',
            'source_type' => 'database',
        ]);
    }
}
