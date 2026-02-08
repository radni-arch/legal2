<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Mcp\OdlukeTools;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive test suite for OdlukeSearchAgent
 *
 * This test suite covers:
 * - Autonomous search execution
 * - Query generation for odluke.sudovi.hr
 * - Result parsing and processing
 * - Data extraction using AI
 * - Error handling and circuit breaker pattern
 * - Rate limiting configuration
 * - Result validation and statistical analysis
 *
 * The OdlukeSearchAgent is the "offensive statistics agent" that collects
 * real data from odluke.sudovi.hr (Croatian court decision database) to feed
 * the StatisticalAnalyzer and HomeSearchAbuseDetector.
 */
class OdlukeSearchAgentTest extends TestCase
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

        // Mock Log facade to prevent actual logging
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        $this->agent = new OdlukeSearchAgent($this->openAIMock, $this->odlukeToolsMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================================================
    // AUTONOMOUS SEARCH EXECUTION TESTS
    // ========================================================================

    /** @test */
    public function it_executes_autonomous_search_for_home_search_cases()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'status' => 'framework_mode',
                'message' => 'Live data integration pending. Use one of these methods:',
                'integration_options' => [],
            ]);

        $result = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('framework_mode', $result['status']);
    }

    /** @test */
    public function it_uses_cache_for_duplicate_searches()
    {
        $cachedResults = [
            'cases' => [
                [
                    'case_number' => 'K-123/2025',
                    'court' => 'Općinski sud u Osijeku',
                ],
            ],
            'total_found' => 1,
            'cached' => true,
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($cachedResults);

        $result = $this->agent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        $this->assertEquals(1, $result['total_found']);
        $this->assertTrue($result['cached']);
    }

    /** @test */
    public function it_searches_with_different_criteria_combinations()
    {
        Cache::shouldReceive('remember')->andReturn([
            'status' => 'framework_mode',
        ]);

        // Test nationwide search
        $result1 = $this->agent->searchHomeSearchCases([
            'region' => 'nationwide',
            'year' => 2025,
        ]);
        $this->assertIsArray($result1);

        // Test court-specific search
        $result2 = $this->agent->searchHomeSearchCases([
            'court' => 'Općinski sud u Osijeku',
            'year' => 2024,
        ]);
        $this->assertIsArray($result2);

        // Test offense-type specific search
        $result3 = $this->agent->searchHomeSearchCases([
            'region' => 'Zagreb',
            'offense_type' => 'prekršaj',
            'year' => 2025,
        ]);
        $this->assertIsArray($result3);
    }

    /** @test */
    public function it_falls_back_to_framework_mode_when_live_search_unavailable()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        $result = $this->agent->searchHomeSearchCases([
            'region' => 'Split',
            'year' => 2025,
        ]);

        $this->assertEquals('framework_mode', $result['status']);
        $this->assertArrayHasKey('integration_options', $result);
        $this->assertArrayHasKey('sample_search_urls', $result);
        $this->assertArrayHasKey('simulated_data_structure', $result);
    }

    // ========================================================================
    // QUERY GENERATION TESTS
    // ========================================================================

    /** @test */
    public function it_builds_search_query_with_home_search_keywords()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeBuildSearchQuery(array $criteria): array
            {
                return $this->buildSearchQuery($criteria);
            }
        };

        $query = $agent->exposeBuildSearchQuery([
            'year' => 2025,
        ]);

        $this->assertStringContainsString('pretres doma', $query['keywords']);
        $this->assertStringContainsString('pretres stana', $query['keywords']);
        $this->assertStringContainsString('pretres prostorija', $query['keywords']);
        $this->assertEquals(2025, $query['year']);
    }

    /** @test */
    public function it_includes_zkp_articles_in_search_query()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeBuildSearchQuery(array $criteria): array
            {
                return $this->buildSearchQuery($criteria);
            }
        };

        $query = $agent->exposeBuildSearchQuery(['year' => 2025]);

        $this->assertStringContainsString('ZKP čl. 215', $query['keywords']);
        $this->assertStringContainsString('ZKP čl. 217', $query['keywords']);
        $this->assertStringContainsString('ZKP čl. 218', $query['keywords']);
        $this->assertStringContainsString('ZKP čl. 179', $query['keywords']);
    }

    /** @test */
    public function it_filters_query_by_region()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeBuildSearchQuery(array $criteria): array
            {
                return $this->buildSearchQuery($criteria);
            }
        };

        $query = $agent->exposeBuildSearchQuery([
            'region' => 'Osijek',
            'year' => 2025,
        ]);

        $this->assertIsArray($query['court']);
        $this->assertContains('Općinski sud u Osijeku', $query['court']);
        $this->assertContains('Županijski sud u Osijeku', $query['court']);
        $this->assertContains('Prekršajni sud u Osijeku', $query['court']);
    }

    /** @test */
    public function it_filters_query_by_offense_type()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeBuildSearchQuery(array $criteria): array
            {
                return $this->buildSearchQuery($criteria);
            }
        };

        // Test misdemeanor filter
        $query1 = $agent->exposeBuildSearchQuery([
            'offense_type' => 'prekršaj',
            'year' => 2025,
        ]);
        $this->assertStringContainsString('prekršaj', $query1['keywords']);
        $this->assertStringContainsString('prekršajna', $query1['keywords']);

        // Test criminal offense filter
        $query2 = $agent->exposeBuildSearchQuery([
            'offense_type' => 'kazneno_djelo',
            'year' => 2025,
        ]);
        $this->assertStringContainsString('kazneno djelo', $query2['keywords']);
        $this->assertStringContainsString('kaznena djela', $query2['keywords']);
    }

    /** @test */
    public function it_gets_regional_courts_for_major_cities()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeGetRegionalCourts(string $region): array
            {
                return $this->getRegionalCourts($region);
            }
        };

        // Test Zagreb
        $zagrebCourts = $agent->exposeGetRegionalCourts('Zagreb');
        $this->assertContains('Općinski građanski sud u Zagrebu', $zagrebCourts);
        $this->assertContains('Općinski kazneni sud u Zagrebu', $zagrebCourts);
        $this->assertContains('Županijski sud u Zagrebu', $zagrebCourts);

        // Test Split
        $splitCourts = $agent->exposeGetRegionalCourts('Split');
        $this->assertContains('Općinski sud u Splitu', $splitCourts);

        // Test Rijeka
        $rijekaCourts = $agent->exposeGetRegionalCourts('Rijeka');
        $this->assertContains('Općinski sud u Rijeci', $rijekaCourts);

        // Test unknown region
        $unknownCourts = $agent->exposeGetRegionalCourts('UnknownCity');
        $this->assertEmpty($unknownCourts);
    }

    // ========================================================================
    // RESULT PARSING TESTS
    // ========================================================================

    /** @test */
    public function it_processes_search_results_with_valid_data()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'case_number' => 'K-123/2025',
                                'court' => 'Općinski sud u Osijeku',
                                'offense_type' => 'prekršaj',
                                'offense_description' => 'Prometni prekršaj',
                            ]),
                        ],
                    ],
                ],
            ]);

        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeProcessSearchResults(array $rawResults, array $criteria): array
            {
                return $this->processSearchResults($rawResults, $criteria);
            }
        };

        $rawResults = [
            [
                'text' => 'Court decision about home search...',
                'url' => 'https://odluke.sudovi.hr/case/123',
            ],
        ];

        $result = $agent->exposeProcessSearchResults($rawResults, ['region' => 'Osijek']);

        $this->assertArrayHasKey('cases', $result);
        $this->assertArrayHasKey('total_found', $result);
        $this->assertArrayHasKey('data_source', $result);
        $this->assertEquals('odluke.sudovi.hr', $result['data_source']);
        $this->assertGreaterThan(0, $result['total_found']);
    }

    /** @test */
    public function it_handles_empty_search_results()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeProcessSearchResults(array $rawResults, array $criteria): array
            {
                return $this->processSearchResults($rawResults, $criteria);
            }
        };

        $result = $agent->exposeProcessSearchResults([], ['region' => 'Osijek']);

        $this->assertEquals(0, $result['total_found']);
        $this->assertEmpty($result['cases']);
    }

    // ========================================================================
    // DATA EXTRACTION TESTS
    // ========================================================================

    /** @test */
    public function it_extracts_case_data_using_ai()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->with(Mockery::type('array'), 'gpt-4o-mini', Mockery::type('array'))
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'case_number' => 'K-456/2025',
                                'court' => 'Općinski sud u Osijeku',
                                'judge' => 'Sudac Ivan Horvat',
                                'date' => '2025-03-15',
                                'offense_type' => 'prekršaj',
                                'offense_description' => 'Prometni prekršaj - vožnja pod utjecajem',
                                'offense_severity' => 'misdemeanor',
                                'search_type' => 'pretres stana',
                                'evidence_found' => true,
                                'evidence_suppressed' => true,
                                'legal_violations' => ['ZKP Čl. 179 - Nerazmjeran pretres'],
                                'zkp_articles_cited' => ['215', '179', '10'],
                                'proportionality_mentioned' => true,
                                'constitutional_rights_mentioned' => true,
                            ]),
                        ],
                    ],
                ],
            ]);

        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }
        };

        $decision = [
            'text' => 'Općinski sud u Osijeku, K-456/2025. Sudac Ivan Horvat...',
            'url' => 'https://odluke.sudovi.hr/case/456',
        ];

        $extracted = $agent->exposeExtractCaseData($decision);

        $this->assertIsArray($extracted);
        $this->assertEquals('K-456/2025', $extracted['case_number']);
        $this->assertEquals('Općinski sud u Osijeku', $extracted['court']);
        $this->assertEquals('prekršaj', $extracted['offense_type']);
        $this->assertTrue($extracted['evidence_suppressed']);
        $this->assertTrue($extracted['proportionality_mentioned']);
        $this->assertArrayHasKey('source_url', $extracted);
        $this->assertArrayHasKey('extraction_date', $extracted);
    }

    /** @test */
    public function it_adds_metadata_to_extracted_case_data()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'case_number' => 'K-789/2025',
                                'court' => 'Županijski sud u Osijeku',
                            ]),
                        ],
                    ],
                ],
            ]);

        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }
        };

        $decision = [
            'text' => 'Court decision text...',
            'url' => 'https://odluke.sudovi.hr/case/789',
        ];

        $extracted = $agent->exposeExtractCaseData($decision);

        $this->assertEquals('https://odluke.sudovi.hr/case/789', $extracted['source_url']);
        $this->assertNotNull($extracted['extraction_date']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $extracted['extraction_date']);
    }

    /** @test */
    public function it_returns_null_for_empty_decision_text()
    {
        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }
        };

        $result1 = $agent->exposeExtractCaseData(['text' => '']);
        $this->assertNull($result1);

        $result2 = $agent->exposeExtractCaseData(['content' => '']);
        $this->assertNull($result2);

        $result3 = $agent->exposeExtractCaseData([]);
        $this->assertNull($result3);
    }

    /** @test */
    public function it_handles_ai_extraction_errors_gracefully()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        Log::shouldReceive('error')
            ->once()
            ->with('OdlukeSearchAgent: Error extracting case data', Mockery::type('array'));

        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }
        };

        $decision = [
            'text' => 'Court decision text...',
        ];

        $result = $agent->exposeExtractCaseData($decision);

        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_invalid_json_response_from_ai()
    {
        $this->openAIMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Not valid JSON',
                        ],
                    ],
                ],
            ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('OdlukeSearchAgent: Failed to extract case data');

        $agent = new class($this->openAIMock, $this->odlukeToolsMock) extends OdlukeSearchAgent
        {
            public function exposeExtractCaseData(array $decision): ?array
            {
                return $this->extractCaseData($decision);
            }
        };

        $decision = ['text' => 'Court decision text...'];
        $result = $agent->exposeExtractCaseData($decision);

        $this->assertNull($result);
    }

    // ========================================================================
    // ERROR HANDLING & CIRCUIT BREAKER TESTS
    // ========================================================================

    /** @test */
    public function it_implements_circuit_breaker_pattern_with_mcp_fallback()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        Log::shouldReceive('debug')
            ->with('OdlukeSearchAgent: MCP tool not available', Mockery::type('array'));

        Log::shouldReceive('debug')
            ->with('OdlukeSearchAgent: Web search failed', Mockery::type('array'));

        $result = $this->agent->searchHomeSearchCases(['region' => 'Osijek']);

        // Should fall back to framework mode
        $this->assertEquals('framework_mode', $result['status']);
        $this->assertArrayHasKey('integration_options', $result);
    }

    /** @test */
    public function it_provides_framework_response_with_integration_options()
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $duration, $callback) {
                return $callback();
            });

        $result = $this->agent->searchHomeSearchCases([
            'region' => 'Zagreb',
            'year' => 2024,
        ]);

        $this->assertArrayHasKey('integration_options', $result);
        $this->assertArrayHasKey('option_1', $result['integration_options']);
        $this->assertArrayHasKey('option_2', $result['integration_options']);
        $this->assertArrayHasKey('option_3', $result['integration_options']);

        $option1 = $result['integration_options']['option_1'];
        $this->assertEquals('MCP Tool Integration', $option1['method']);
        $this->assertEquals('high', $option1['priority']);
    }

    // ========================================================================
    // RATE LIMITING TESTS
    // ========================================================================

    /** @test */
    public function it_has_configured_rate_limit_of_10_requests_per_minute()
    {
        $reflection = new \ReflectionClass($this->agent);
        $property = $reflection->getProperty('maxRequestsPerMinute');
        $property->setAccessible(true);

        $rateLimit = $property->getValue($this->agent);

        $this->assertEquals(10, $rateLimit);
    }

    /** @test */
    public function it_has_configured_cache_duration_of_one_week()
    {
        $reflection = new \ReflectionClass($this->agent);
        $property = $reflection->getProperty('cacheDuration');
        $property->setAccessible(true);

        $cacheDuration = $property->getValue($this->agent);

        $this->assertEquals(604800, $cacheDuration); // 7 days * 24 hours * 60 minutes * 60 seconds
    }

    // ========================================================================
    // RESULT VALIDATION & STATISTICAL ANALYSIS TESTS
    // ========================================================================

    /** @test */
    public function it_analyzes_extracted_cases_and_generates_statistics()
    {
        $cases = [
            [
                'case_number' => 'K-1/2025',
                'court' => 'Općinski sud u Osijeku',
                'offense_type' => 'prekršaj',
                'date' => '2025-01-15',
                'evidence_found' => true,
                'evidence_suppressed' => false,
                'proportionality_mentioned' => false,
                'constitutional_rights_mentioned' => false,
            ],
            [
                'case_number' => 'K-2/2025',
                'court' => 'Županijski sud u Osijeku',
                'offense_type' => 'kazneno_djelo',
                'date' => '2025-02-20',
                'evidence_found' => false,
                'evidence_suppressed' => false,
                'proportionality_mentioned' => true,
                'constitutional_rights_mentioned' => true,
            ],
            [
                'case_number' => 'K-3/2025',
                'court' => 'Općinski sud u Osijeku',
                'offense_type' => 'prekršaj',
                'date' => '2025-03-10',
                'evidence_found' => true,
                'evidence_suppressed' => 'yes',
                'proportionality_mentioned' => true,
                'constitutional_rights_mentioned' => false,
            ],
        ];

        $analysis = $this->agent->analyzeExtractedCases($cases);

        $this->assertEquals(3, $analysis['total_cases']);
        $this->assertEquals(2, $analysis['by_offense_type']['prekršaj']);
        $this->assertEquals(1, $analysis['by_offense_type']['kazneno_djelo']);
        $this->assertEquals(66.7, $analysis['evidence_found_rate']);
        $this->assertEquals(33.3, $analysis['suppression_rate']);
        $this->assertEquals(2, $analysis['proportionality_issues']);
        $this->assertEquals(1, $analysis['constitutional_issues']);
    }

    /** @test */
    public function it_groups_cases_by_court_and_year()
    {
        $cases = [
            [
                'court' => 'Općinski sud u Osijeku',
                'date' => '2024-05-15',
                'offense_type' => 'prekršaj',
            ],
            [
                'court' => 'Općinski sud u Osijeku',
                'date' => '2024-08-20',
                'offense_type' => 'prekršaj',
            ],
            [
                'court' => 'Županijski sud u Osijeku',
                'date' => '2025-01-10',
                'offense_type' => 'kazneno_djelo',
            ],
        ];

        $analysis = $this->agent->analyzeExtractedCases($cases);

        $this->assertEquals(2, $analysis['by_court']['Općinski sud u Osijeku']);
        $this->assertEquals(1, $analysis['by_court']['Županijski sud u Osijeku']);
        $this->assertEquals(2, $analysis['by_year']['2024']);
        $this->assertEquals(1, $analysis['by_year']['2025']);
    }

    /** @test */
    public function it_identifies_alarming_finding_for_high_misdemeanor_rate()
    {
        $cases = array_fill(0, 10, [
            'offense_type' => 'prekršaj',
            'court' => 'Općinski sud u Osijeku',
            'date' => '2025-01-15',
            'evidence_found' => false,
            'evidence_suppressed' => false,
        ]);

        $analysis = $this->agent->analyzeExtractedCases($cases);

        $this->assertArrayHasKey('alarming_findings', $analysis);
        $this->assertNotEmpty($analysis['alarming_findings']);

        $alarmingText = implode(' ', $analysis['alarming_findings']);
        $this->assertStringContainsString('prekršajima', $alarmingText);
        $this->assertStringContainsString('100', $alarmingText); // 100% misdemeanors
    }

    /** @test */
    public function it_identifies_alarming_finding_for_high_suppression_rate()
    {
        $cases = [];

        // 18 cases with suppressed evidence
        for ($i = 0; $i < 18; $i++) {
            $cases[] = [
                'offense_type' => 'kazneno_djelo',
                'court' => 'Općinski sud u Osijeku',
                'date' => '2025-01-15',
                'evidence_found' => true,
                'evidence_suppressed' => 'yes',
            ];
        }

        // 2 cases without suppression
        for ($i = 0; $i < 2; $i++) {
            $cases[] = [
                'offense_type' => 'kazneno_djelo',
                'court' => 'Općinski sud u Osijeku',
                'date' => '2025-01-15',
                'evidence_found' => true,
                'evidence_suppressed' => false,
            ];
        }

        $analysis = $this->agent->analyzeExtractedCases($cases);

        $this->assertGreaterThan(15, $analysis['suppression_rate']);

        $alarmingText = implode(' ', $analysis['alarming_findings']);
        $this->assertStringContainsString('isključenja dokaza', $alarmingText);
        $this->assertStringContainsString('zabrinjavajuće', $alarmingText);
    }

    /** @test */
    public function it_identifies_alarming_finding_for_low_evidence_found_rate()
    {
        $cases = [];

        // 60 cases with no evidence found
        for ($i = 0; $i < 60; $i++) {
            $cases[] = [
                'offense_type' => 'kazneno_djelo',
                'court' => 'Općinski sud u Osijeku',
                'date' => '2025-01-15',
                'evidence_found' => false,
                'evidence_suppressed' => false,
            ];
        }

        // 40 cases with evidence
        for ($i = 0; $i < 40; $i++) {
            $cases[] = [
                'offense_type' => 'kazneno_djelo',
                'court' => 'Općinski sud u Osijeku',
                'date' => '2025-01-15',
                'evidence_found' => 'yes',
                'evidence_suppressed' => false,
            ];
        }

        $analysis = $this->agent->analyzeExtractedCases($cases);

        $this->assertLessThan(50, $analysis['evidence_found_rate']);

        $alarmingText = implode(' ', $analysis['alarming_findings']);
        $this->assertStringContainsString('Niska stopa pronalaska dokaza', $alarmingText);
        $this->assertStringContainsString('osnovanoj sumnji', $alarmingText);
    }

    /** @test */
    public function it_handles_empty_case_list_in_analysis()
    {
        $analysis = $this->agent->analyzeExtractedCases([]);

        $this->assertEquals(0, $analysis['total_cases']);
        $this->assertEquals('No cases found', $analysis['analysis']);
    }
}
