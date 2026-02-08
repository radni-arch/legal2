<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Mcp\OdlukeTools;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Sprint 2.2: OdlukeSearchAgent Data Extraction Tests
 *
 * Tests for improved extraction accuracy, validation, confidence scoring,
 * and partial extraction fallback.
 *
 * Acceptance Criteria:
 * - Extraction accuracy >90% on benchmark (20 test cases)
 * - All 14 fields extracted correctly
 * - Confidence score correlates with accuracy
 * - Partial failures handled gracefully
 */
class OdlukeSearchAgentExtractionTest extends TestCase
{
    use UsesTestDatabase;

    protected OdlukeSearchAgent $agent;

    protected $openAIMock;

    protected $odlukeToolsMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->odlukeToolsMock = Mockery::mock(OdlukeTools::class);

        // Mock Log facade
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        // Mock OpenAI API for offline testing
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'case_number' => 'K-123/2025',
                                'court' => 'Općinski sud u Osijeku',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->agent = new OdlukeSearchAgent($this->openAIMock, $this->odlukeToolsMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // BENCHMARK TESTS: 20 Real-World Extraction Scenarios (>90% Accuracy)
    // ========================================================================

    /**
     * Benchmark 1: Simple misdemeanor traffic offense with clear extraction
     */
    public function test_benchmark_01_simple_misdemeanor_traffic_offense()
    {
        $decisionText = <<<'TEXT'
OPĆINSKI SUD U OSIJEKU
PREKRŠAJNI ODJEL
Broj predmeta: P-1234/2024
Sudac: Ana Kovač

PRESUDA

U prekršajnom postupku protiv okrivljenika Ivana Horvata zbog prekršaja iz
članka 223. Zakona o sigurnosti prometa na cestama (vožnja pod utjecajem alkohola),
sud je odlučio:

Okrivljenik je KRIV.

OBRAZLOŽENJE:
Dana 15.01.2024. godine izvršen je pretres stana okrivljenika temeljem naloga za
pretres iz ZKP članak 215. Pretresom je pronađena kradena vozačka dozvola.

Međutim, sud je isključio dokaze jer pretres stana nije bio razmjeran prekršaju
vožnje pod utjecajem alkohola (ZKP članak 179. - načelo razmjernosti).

Ustavom RH članak 34. jamči se nepovredivost stana.
TEXT;

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'case_number' => 'P-1234/2024',
                        'court' => 'Općinski sud u Osijeku',
                        'judge' => 'Ana Kovač',
                        'date' => '2024-01-15',
                        'offense_type' => 'prekršaj',
                        'offense_description' => 'Vožnja pod utjecajem alkohola',
                        'offense_severity' => 'misdemeanor',
                        'search_type' => 'pretres stana',
                        'evidence_found' => 'yes',
                        'evidence_suppressed' => 'yes',
                        'legal_violations' => ['ZKP Čl. 179 - Nerazmjeran pretres'],
                        'zkp_articles_cited' => ['215', '179'],
                        'proportionality_mentioned' => true,
                        'constitutional_rights_mentioned' => true,
                        'confidence' => 0.95,
                    ])],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $result = $agent->exposeExtractCaseData(['text' => $decisionText]);

        $this->assertExtractionAccuracy($result, [
            'case_number' => 'P-1234/2024',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Ana Kovač',
            'offense_type' => 'prekršaj',
            'evidence_suppressed' => 'yes',
            'proportionality_mentioned' => true,
            'constitutional_rights_mentioned' => true,
        ]);

        $this->assertGreaterThanOrEqual(0.9, $result['confidence'] ?? 0);
    }

    /**
     * Benchmark 2: Serious criminal offense with justified search
     */
    public function test_benchmark_02_serious_criminal_offense_justified_search()
    {
        $decisionText = <<<'TEXT'
ŽUPANIJSKI SUD U OSIJEKU
KAZNENI ODJEL
K-789/2024
Sudac: Marko Babić

PRESUDA

Okrivljenik Ivan Novak kriv je za kazneno djelo nedopuštene trgovine drogom
iz članka 190. Kaznenog zakona.

Dana 20.03.2024. izvršen je pretres doma okrivljenika temeljem ZKP članak 215.
i 217. Pretresom je pronađeno 500g heroina. Dokazi su prihvaćeni jer je pretres
bio razmjeran težini kaznenog djela.
TEXT;

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'case_number' => 'K-789/2024',
                        'court' => 'Županijski sud u Osijeku',
                        'judge' => 'Marko Babić',
                        'date' => '2024-03-20',
                        'offense_type' => 'kazneno_djelo',
                        'offense_description' => 'Nedopuštena trgovina drogom',
                        'offense_severity' => 'serious',
                        'search_type' => 'pretres doma',
                        'evidence_found' => 'yes',
                        'evidence_suppressed' => 'no',
                        'legal_violations' => null,
                        'zkp_articles_cited' => ['215', '217'],
                        'proportionality_mentioned' => true,
                        'constitutional_rights_mentioned' => false,
                        'confidence' => 0.92,
                    ])],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $result = $agent->exposeExtractCaseData(['text' => $decisionText]);

        $this->assertExtractionAccuracy($result, [
            'case_number' => 'K-789/2024',
            'offense_type' => 'kazneno_djelo',
            'offense_severity' => 'serious',
            'evidence_found' => 'yes',
            'evidence_suppressed' => 'no',
        ]);

        $this->assertGreaterThanOrEqual(0.9, $result['confidence'] ?? 0);
    }

    /**
     * Benchmark 3: Missing judge name - partial extraction
     */
    public function test_benchmark_03_partial_extraction_missing_judge()
    {
        $decisionText = <<<'TEXT'
OPĆINSKI SUD U SPLITU
P-5678/2025

RJEŠENJE

U prekršajnom postupku protiv okrivljenika Petra Marića,
sud je izvršio pretres prostorija dana 10.02.2025.
Temelj: ZKP članak 215. i 218.
Pronađeno: Ukradeni novčanik.
TEXT;

        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'case_number' => 'P-5678/2025',
                        'court' => 'Općinski sud u Splitu',
                        'judge' => null, // Missing
                        'date' => '2025-02-10',
                        'offense_type' => 'prekršaj',
                        'offense_description' => 'Unknown',
                        'offense_severity' => 'misdemeanor',
                        'search_type' => 'pretres prostorija',
                        'evidence_found' => 'yes',
                        'evidence_suppressed' => null,
                        'legal_violations' => null,
                        'zkp_articles_cited' => ['215', '218'],
                        'proportionality_mentioned' => false,
                        'constitutional_rights_mentioned' => false,
                        'confidence' => 0.65, // Lower confidence due to missing data
                    ])],
                ]],
            ]);

        $agent = $this->createExtendedAgent();
        $result = $agent->exposeExtractCaseData(['text' => $decisionText]);

        // Should still extract available fields
        $this->assertEquals('P-5678/2025', $result['case_number']);
        $this->assertEquals('Općinski sud u Splitu', $result['court']);
        $this->assertNull($result['judge']);

        // Confidence should reflect missing data
        $this->assertLessThan(0.8, $result['confidence'] ?? 1);
        $this->assertGreaterThan(0.5, $result['confidence'] ?? 0);

        // Should have validation warnings
        $this->assertArrayHasKey('validation_warnings', $result);
        $this->assertContains('missing_judge', $result['validation_warnings']);
    }

    /**
     * Benchmark 4-20: Additional benchmark cases
     * (Creating 20 total benchmark cases for >90% accuracy requirement)
     */
    public function test_benchmark_04_constitutional_violation_explicit()
    {
        $this->markTestIncomplete('Benchmark test 4 pending implementation');
    }

    public function test_benchmark_05_multiple_zkp_articles()
    {
        $this->markTestIncomplete('Benchmark test 5 pending implementation');
    }

    public function test_benchmark_06_zagreb_regional_court()
    {
        $this->markTestIncomplete('Benchmark test 6 pending implementation');
    }

    public function test_benchmark_07_no_evidence_found()
    {
        $this->markTestIncomplete('Benchmark test 7 pending implementation');
    }

    public function test_benchmark_08_medium_severity_offense()
    {
        $this->markTestIncomplete('Benchmark test 8 pending implementation');
    }

    public function test_benchmark_09_pretres_vozila()
    {
        $this->markTestIncomplete('Benchmark test 9 pending implementation');
    }

    public function test_benchmark_10_complex_legal_violations()
    {
        $this->markTestIncomplete('Benchmark test 10 pending implementation');
    }

    public function test_benchmark_11_ambiguous_offense_severity()
    {
        $this->markTestIncomplete('Benchmark test 11 pending implementation');
    }

    public function test_benchmark_12_suppression_without_explicit_mention()
    {
        $this->markTestIncomplete('Benchmark test 12 pending implementation');
    }

    public function test_benchmark_13_proportionality_implicit()
    {
        $this->markTestIncomplete('Benchmark test 13 pending implementation');
    }

    public function test_benchmark_14_multiple_search_types()
    {
        $this->markTestIncomplete('Benchmark test 14 pending implementation');
    }

    public function test_benchmark_15_rijeka_court_decision()
    {
        $this->markTestIncomplete('Benchmark test 15 pending implementation');
    }

    public function test_benchmark_16_old_format_decision_2020()
    {
        $this->markTestIncomplete('Benchmark test 16 pending implementation');
    }

    public function test_benchmark_17_night_search_zkp_218()
    {
        $this->markTestIncomplete('Benchmark test 17 pending implementation');
    }

    public function test_benchmark_18_evidence_found_but_unrelated()
    {
        $this->markTestIncomplete('Benchmark test 18 pending implementation');
    }

    public function test_benchmark_19_prosecutor_misconduct_mentioned()
    {
        $this->markTestIncomplete('Benchmark test 19 pending implementation');
    }

    public function test_benchmark_20_appeal_decision_second_instance()
    {
        $this->markTestIncomplete('Benchmark test 20 pending implementation');
    }

    // ========================================================================
    // FIELD VALIDATION TESTS
    // ========================================================================

    /**
     * @test
     */
    public function it_validates_case_number_format()
    {
        $result = [
            'case_number' => 'INVALID',
            'court' => 'Općinski sud u Osijeku',
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_errors', $validated);
        $this->assertContains('invalid_case_number_format', $validated['validation_errors']);
    }

    /**
     * @test
     */
    public function it_validates_all_14_required_fields_present()
    {
        $result = [
            'case_number' => 'K-123/2025',
            // Missing 13 other fields
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_warnings', $validated);
        $this->assertGreaterThanOrEqual(10, count($validated['validation_warnings']));
    }

    /**
     * @test
     */
    public function it_validates_offense_type_enum()
    {
        $result = [
            'offense_type' => 'invalid_type',
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_errors', $validated);
        $this->assertContains('invalid_offense_type', $validated['validation_errors']);
    }

    /**
     * @test
     */
    public function it_validates_offense_severity_enum()
    {
        $result = [
            'offense_severity' => 'very_serious', // Invalid
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_errors', $validated);
        $this->assertContains('invalid_offense_severity', $validated['validation_errors']);
    }

    /**
     * @test
     */
    public function it_validates_date_format()
    {
        $result = [
            'date' => '15/01/2024', // Invalid format
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_errors', $validated);
        $this->assertContains('invalid_date_format', $validated['validation_errors']);
    }

    /**
     * @test
     */
    public function it_validates_zkp_articles_are_numeric()
    {
        $result = [
            'zkp_articles_cited' => ['215', 'invalid', '179'],
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertArrayHasKey('validation_warnings', $validated);
        $this->assertContains('invalid_zkp_article', $validated['validation_warnings']);
    }

    /**
     * @test
     */
    public function it_passes_validation_for_perfect_extraction()
    {
        $result = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Ana Kovač',
            'date' => '2025-01-15',
            'offense_type' => 'prekršaj',
            'offense_description' => 'Vožnja pod utjecajem',
            'offense_severity' => 'misdemeanor',
            'search_type' => 'pretres stana',
            'evidence_found' => 'yes',
            'evidence_suppressed' => 'yes',
            'legal_violations' => ['ZKP Čl. 179'],
            'zkp_articles_cited' => ['215', '179'],
            'proportionality_mentioned' => true,
            'constitutional_rights_mentioned' => true,
        ];

        $agent = $this->createExtendedAgent();
        $validated = $agent->exposeValidateExtractedFields($result);

        $this->assertEmpty($validated['validation_errors'] ?? []);
        $this->assertEmpty($validated['validation_warnings'] ?? []);
    }

    // ========================================================================
    // CONFIDENCE SCORING TESTS
    // ========================================================================

    /**
     * @test
     */
    public function it_calculates_confidence_score_based_on_completeness()
    {
        $fullData = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Ana Kovač',
            'date' => '2025-01-15',
            'offense_type' => 'prekršaj',
            'offense_description' => 'Vožnja pod utjecajem',
            'offense_severity' => 'misdemeanor',
            'search_type' => 'pretres stana',
            'evidence_found' => 'yes',
            'evidence_suppressed' => 'yes',
            'legal_violations' => ['ZKP Čl. 179'],
            'zkp_articles_cited' => ['215', '179'],
            'proportionality_mentioned' => true,
            'constitutional_rights_mentioned' => true,
        ];

        $partialData = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => null,
            'date' => null,
        ];

        $agent = $this->createExtendedAgent();

        $fullScore = $agent->exposeCalculateConfidenceScore($fullData);
        $partialScore = $agent->exposeCalculateConfidenceScore($partialData);

        $this->assertGreaterThan(0.9, $fullScore);
        $this->assertLessThan(0.5, $partialScore);
        $this->assertGreaterThan($partialScore, $fullScore);
    }

    /**
     * @test
     */
    public function it_reduces_confidence_for_validation_errors()
    {
        $validData = [
            'case_number' => 'K-123/2025',
            'offense_type' => 'prekršaj',
            'date' => '2025-01-15',
        ];

        $invalidData = [
            'case_number' => 'INVALID-FORMAT',
            'offense_type' => 'unknown_type',
            'date' => '15/01/2025',
        ];

        $agent = $this->createExtendedAgent();

        $validScore = $agent->exposeCalculateConfidenceScore($validData);
        $invalidScore = $agent->exposeCalculateConfidenceScore($invalidData);

        $this->assertGreaterThan($invalidScore, $validScore);
        $this->assertLessThan(0.6, $invalidScore);
    }

    /**
     * @test
     */
    public function it_confidence_score_correlates_with_extraction_quality()
    {
        $highQuality = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Ana Kovač',
            'date' => '2025-01-15',
            'offense_type' => 'prekršaj',
            'offense_description' => 'Detailed description',
            'offense_severity' => 'misdemeanor',
            'search_type' => 'pretres stana',
            'evidence_found' => 'yes',
            'evidence_suppressed' => 'yes',
            'legal_violations' => ['ZKP Čl. 179', 'ZKP Čl. 10'],
            'zkp_articles_cited' => ['215', '179', '10'],
            'proportionality_mentioned' => true,
            'constitutional_rights_mentioned' => true,
        ];

        $mediumQuality = [
            'case_number' => 'K-456/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => null,
            'date' => '2025-01-15',
            'offense_type' => 'prekršaj',
            'offense_severity' => 'misdemeanor',
        ];

        $lowQuality = [
            'case_number' => 'K-789/2025',
            'court' => null,
        ];

        $agent = $this->createExtendedAgent();

        $highScore = $agent->exposeCalculateConfidenceScore($highQuality);
        $mediumScore = $agent->exposeCalculateConfidenceScore($mediumQuality);
        $lowScore = $agent->exposeCalculateConfidenceScore($lowQuality);

        $this->assertGreaterThan(0.85, $highScore);
        $this->assertGreaterThan(0.5, $mediumScore);
        $this->assertLessThan(0.7, $mediumScore);
        $this->assertLessThan(0.4, $lowScore);

        $this->assertGreaterThan($mediumScore, $highScore);
        $this->assertGreaterThan($lowScore, $mediumScore);
    }

    // ========================================================================
    // PARTIAL EXTRACTION FALLBACK TESTS
    // ========================================================================

    /**
     * @test
     */
    public function it_handles_partial_extraction_gracefully()
    {
        $partialResult = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => null,
            'date' => null,
            'offense_type' => 'prekršaj',
        ];

        $agent = $this->createExtendedAgent();
        $processed = $agent->exposeHandlePartialExtraction($partialResult);

        $this->assertIsArray($processed);
        $this->assertEquals('partial', $processed['extraction_status']);
        $this->assertArrayHasKey('missing_fields', $processed);
        $this->assertContains('judge', $processed['missing_fields']);
        $this->assertContains('date', $processed['missing_fields']);
    }

    /**
     * @test
     */
    public function it_fills_missing_fields_with_fallback_values()
    {
        $partialResult = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
        ];

        $agent = $this->createExtendedAgent();
        $processed = $agent->exposeHandlePartialExtraction($partialResult);

        $this->assertArrayHasKey('judge', $processed);
        $this->assertArrayHasKey('date', $processed);
        $this->assertArrayHasKey('offense_type', $processed);
        $this->assertArrayHasKey('evidence_found', $processed);

        // Fallback values should be null, not missing
        $this->assertNull($processed['judge']);
        $this->assertNull($processed['date']);
    }

    /**
     * @test
     */
    public function it_rejects_extraction_if_critical_fields_missing()
    {
        $criticalMissing = [
            'judge' => 'Ana Kovač',
            'date' => '2025-01-15',
            // Missing case_number and court (critical fields)
        ];

        $agent = $this->createExtendedAgent();
        $processed = $agent->exposeHandlePartialExtraction($criticalMissing);

        $this->assertEquals('failed', $processed['extraction_status']);
        $this->assertArrayHasKey('error', $processed);
        $this->assertStringContainsString('critical fields missing', $processed['error']);
    }

    /**
     * @test
     */
    public function it_logs_warnings_for_low_confidence_extractions()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Low confidence extraction', Mockery::on(function ($arg) {
                return isset($arg['confidence']) && $arg['confidence'] < 0.7;
            }));

        $lowConfidenceData = [
            'case_number' => 'K-123/2025',
            'court' => null,
            'confidence' => 0.45,
        ];

        $agent = $this->createExtendedAgent();
        $agent->exposeHandlePartialExtraction($lowConfidenceData);
    }

    // ========================================================================
    // EXTRACTION ACCURACY MEASUREMENT TESTS
    // ========================================================================

    /**
     * @test
     */
    public function it_measures_extraction_accuracy_across_benchmarks()
    {
        $agent = $this->createExtendedAgent();

        // This test will run all 20 benchmark tests and measure accuracy
        $benchmarkResults = $agent->exposeRunBenchmarkSuite();

        $this->assertIsArray($benchmarkResults);
        $this->assertArrayHasKey('total_cases', $benchmarkResults);
        $this->assertArrayHasKey('successful_extractions', $benchmarkResults);
        $this->assertArrayHasKey('accuracy_percentage', $benchmarkResults);

        // Must achieve >90% accuracy
        $this->assertGreaterThanOrEqual(90.0, $benchmarkResults['accuracy_percentage']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    protected function createExtendedAgent()
    {
        return new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }

            public function exposeValidateExtractedFields(array $data): array
            {
                return $this->validateExtractedFields($data);
            }

            public function exposeCalculateConfidenceScore(array $data): float
            {
                return $this->calculateConfidenceScore($data);
            }

            public function exposeHandlePartialExtraction(array $data): array
            {
                return $this->handlePartialExtraction($data);
            }

            public function exposeRunBenchmarkSuite(): array
            {
                return $this->runBenchmarkSuite();
            }
        };
    }

    protected function assertExtractionAccuracy(array $result, array $expected): void
    {
        foreach ($expected as $field => $value) {
            $this->assertEquals(
                $value,
                $result[$field] ?? null,
                "Field '{$field}' extraction mismatch"
            );
        }
    }
}
