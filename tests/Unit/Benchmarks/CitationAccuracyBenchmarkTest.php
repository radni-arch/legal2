<?php

namespace Tests\Unit\Benchmarks;

use App\Benchmarks\CitationAccuracyBenchmark;
use App\Services\LawVectorStoreService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CitationAccuracyBenchmarkTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some test laws
        DB::table('laws')->insert([
            [
                'id' => '01TEST1000000000000000000',
                'doc_id' => 'ZKP-09',
                'law_number' => 'ZKP-09',
                'title' => 'Zakon o kaznenom postupku - Članak 9',
                'chunk_index' => 0,
                'content' => 'Presumption of innocence...',
                'content_hash' => hash('sha256', 'Presumption of innocence...'),
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => '01TEST2000000000000000000',
                'doc_id' => 'USTAV-29',
                'law_number' => 'USTAV-29',
                'title' => 'Ustav Republike Hrvatske - Članak 29',
                'chunk_index' => 0,
                'content' => 'Right to fair trial...',
                'content_hash' => hash('sha256', 'Right to fair trial...'),
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /** @test */
    public function it_has_correct_name_and_description()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $this->assertEquals('Citation Accuracy', $benchmark->getName());
        $this->assertStringContainsString('accuracy', strtolower($benchmark->getDescription()));
    }

    /** @test */
    public function it_runs_citation_accuracy_benchmark()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $run = $benchmark->run();

        $this->assertEquals('completed', $run->status);
        $this->assertArrayHasKey('total_tests', $run->metrics);
        $this->assertArrayHasKey('extraction_accuracy', $run->metrics);
        $this->assertArrayHasKey('precision', $run->metrics);
        $this->assertArrayHasKey('recall', $run->metrics);
        $this->assertArrayHasKey('f1_score', $run->metrics);
    }

    /** @test */
    public function it_extracts_croatian_citations_correctly()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($benchmark);
        $method = $reflection->getMethod('extractCitations');
        $method->setAccessible(true);

        $text = 'According to ZKP Article 9, the presumption of innocence applies.';
        $citations = $method->invoke($benchmark, $text);

        $this->assertContains('ZKP Article 9', $citations);
        $this->assertContains('ZKP Članak 9', $citations);
    }

    /** @test */
    public function it_extracts_multiple_citations()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $reflection = new \ReflectionClass($benchmark);
        $method = $reflection->getMethod('extractCitations');
        $method->setAccessible(true);

        $text = 'According to ZKP Article 9 and Ustav Article 29, rights are protected.';
        $citations = $method->invoke($benchmark, $text);

        $this->assertGreaterThanOrEqual(2, count($citations));
        $this->assertTrue(in_array('ZKP Article 9', $citations) || in_array('ZKP Članak 9', $citations));
        $this->assertTrue(in_array('Ustav Article 29', $citations) || in_array('Ustav Članak 29', $citations));
    }

    /** @test */
    public function it_handles_full_law_names()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $reflection = new \ReflectionClass($benchmark);
        $method = $reflection->getMethod('extractCitations');
        $method->setAccessible(true);

        $text = 'Zakon o kaznenom postupku Article 9 provides...';
        $citations = $method->invoke($benchmark, $text);

        $this->assertContains('ZKP Article 9', $citations);
    }

    /** @test */
    public function it_calculates_accuracy_metrics()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $config = [
            'sample_size' => 10,
            'test_cases' => [
                [
                    'text' => 'ZKP Article 9 test',
                    'expected' => ['ZKP Article 9', 'ZKP Članak 9'],
                ],
                [
                    'text' => 'Ustav Article 29 test',
                    'expected' => ['Ustav Article 29', 'Ustav Članak 29'],
                ],
            ],
        ];

        $benchmark->setConfig($config);
        $run = $benchmark->run();

        $this->assertGreaterThan(0, $run->metrics['total_tests']);
        $this->assertGreaterThanOrEqual(0, $run->metrics['extraction_accuracy']);
        $this->assertLessThanOrEqual(1, $run->metrics['extraction_accuracy']);
    }

    /** @test */
    public function it_considers_lower_error_rates_as_improvement()
    {
        $lawVectorStore = Mockery::mock(LawVectorStoreService::class);
        $benchmark = new CitationAccuracyBenchmark($lawVectorStore);

        $reflection = new \ReflectionClass($benchmark);
        $method = $reflection->getMethod('isImprovement');
        $method->setAccessible(true);

        // False positives: lower is better
        $this->assertTrue($method->invoke($benchmark, 'false_positives', -5));
        $this->assertFalse($method->invoke($benchmark, 'false_positives', 5));

        // Accuracy: higher is better
        $this->assertTrue($method->invoke($benchmark, 'extraction_accuracy', 0.1));
        $this->assertFalse($method->invoke($benchmark, 'extraction_accuracy', -0.1));
    }
}
