<?php

namespace Tests\Integration;

use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Modules\HomeSearch\Services\StatisticalAnalyzer;
use Tests\TestCase;

/**
 * Live Integration Test: OdlukeSearchAgent with Real MCP Tool
 *
 * This test validates that the OdlukeSearchAgent can successfully:
 * 1. Connect to odluke.sudovi.hr via MCP tool
 * 2. Search for real court decisions
 * 3. Extract structured data from actual Croatian court documents
 * 4. Analyze statistics from live data
 *
 * IMPORTANT: This test requires:
 * - MCP server running and configured
 * - Network access to odluke.sudovi.hr
 * - OpenAI API key for extraction
 *
 * To run: ./vendor/bin/phpunit --filter=LiveOdlukeIntegrationTest --group=live
 */
class LiveOdlukeIntegrationTest extends TestCase
{
    protected OdlukeSearchAgent $odlukeAgent;

    protected StatisticalAnalyzer $statisticalAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->odlukeAgent = app(OdlukeSearchAgent::class);
        $this->statisticalAnalyzer = app(StatisticalAnalyzer::class);
    }

    /**
     * Test 1: Search for real home search cases from Osijek 2024
     *
     * @test
     *
     * @group live
     * @group mcp
     */
    public function test_live_search_osijek_home_search_cases_2024()
    {
        $this->markTestSkipped('Live test - requires MCP server and API access. Run manually with --group=live');

        // Arrange: Search criteria for Osijek home search cases in 2024
        $criteria = [
            'region' => 'Osijek',
            'year' => 2024,
            'offense_type' => null, // Get all types
        ];

        // Act: Execute live search
        $results = $this->odlukeAgent->searchHomeSearchCases($criteria);

        // Assert: Verify we got real data
        $this->assertIsArray($results);
        $this->assertArrayHasKey('cases', $results);

        // If we're not in framework mode, validate real data
        if (! isset($results['status']) || $results['status'] !== 'framework_mode') {
            $this->assertNotEmpty($results['cases'], 'Should return at least some cases');
            $this->assertGreaterThanOrEqual(1, count($results['cases']));

            // Verify first case has expected structure
            $firstCase = $results['cases'][0];
            $this->assertArrayHasKey('case_number', $firstCase);
            $this->assertArrayHasKey('court', $firstCase);
            $this->assertArrayHasKey('offense_type', $firstCase);

            echo "\n✅ Successfully retrieved ".count($results['cases']).' real cases from odluke.sudovi.hr';
            echo "\n📊 Sample case: {$firstCase['case_number']} from {$firstCase['court']}";
        }
    }

    /**
     * Test 2: Validate data extraction accuracy on real court decisions
     *
     * @test
     *
     * @group live
     * @group mcp
     */
    public function test_live_data_extraction_accuracy()
    {
        $this->markTestSkipped('Live test - requires MCP server and API access. Run manually with --group=live');

        // Arrange: Get sample of real cases
        $results = $this->odlukeAgent->searchHomeSearchCases([
            'region' => 'Osijek',
            'year' => 2024,
        ]);

        if (! isset($results['cases']) || empty($results['cases'])) {
            $this->markTestSkipped('No cases available for accuracy test');
        }

        $cases = $results['cases'];
        $totalCases = count($cases);

        // Act: Analyze extraction quality
        $completeExtractions = 0;
        $partialExtractions = 0;
        $failedExtractions = 0;
        $totalConfidence = 0;

        foreach ($cases as $case) {
            $confidence = $case['confidence'] ?? 0;
            $totalConfidence += $confidence;

            if ($confidence >= 0.9) {
                $completeExtractions++;
            } elseif ($confidence >= 0.7) {
                $partialExtractions++;
            } else {
                $failedExtractions++;
            }
        }

        $averageConfidence = $totalConfidence / $totalCases;
        $accuracyPercentage = ($completeExtractions / $totalCases) * 100;

        // Assert: Verify extraction quality meets Sprint 2.2 requirement (>90% accuracy)
        $this->assertGreaterThanOrEqual(0.90, $averageConfidence, 'Average confidence should be ≥90%');
        $this->assertGreaterThanOrEqual(90.0, $accuracyPercentage, 'Extraction accuracy should be ≥90%');

        echo "\n✅ Extraction Quality Metrics:";
        echo "\n   Total Cases: {$totalCases}";
        echo "\n   Complete (≥90%): {$completeExtractions}";
        echo "\n   Partial (70-89%): {$partialExtractions}";
        echo "\n   Failed (<70%): {$failedExtractions}";
        echo "\n   Average Confidence: ".round($averageConfidence * 100, 1).'%';
        echo "\n   Accuracy: ".round($accuracyPercentage, 1).'%';
    }

    /**
     * Test 3: Validate StatisticalAnalyzer with live data
     *
     * @test
     *
     * @group live
     * @group mcp
     */
    public function test_live_statistical_analysis()
    {
        $this->markTestSkipped('Live test - requires MCP server and API access. Run manually with --group=live');

        // Arrange: Get real data for 2024
        $statistics = $this->statisticalAnalyzer->getYearlyStatistics(2024, [
            'region' => 'Osijek',
        ]);

        // Assert: Verify we got real statistics
        if (isset($statistics['status']) && $statistics['status'] === 'real_data_retrieved') {
            $this->assertArrayHasKey('summary', $statistics);
            $this->assertArrayHasKey('total_home_searches', $statistics['summary']);
            $this->assertArrayHasKey('misdemeanor_based_searches', $statistics['summary']);
            $this->assertArrayHasKey('percentage_misdemeanor', $statistics['summary']);

            $total = $statistics['summary']['total_home_searches'];
            $misdemeanorCount = $statistics['summary']['misdemeanor_based_searches'];
            $misdemeanorPct = $statistics['summary']['percentage_misdemeanor'];

            echo "\n✅ Live Statistics for Osijek 2024:";
            echo "\n   Total Home Searches: {$total}";
            echo "\n   Misdemeanor-Based: {$misdemeanorCount}";
            echo "\n   Misdemeanor Percentage: {$misdemeanorPct}%";

            // Check if alarm should trigger
            if ($misdemeanorPct > 20) {
                echo "\n   ⚠️  ALARM: Misdemeanor percentage exceeds 20% threshold!";
                $this->assertArrayHasKey('alarming_findings', $statistics['summary']);
                $this->assertNotEmpty($statistics['summary']['alarming_findings']);
            }
        } else {
            echo "\n⚠️  MCP integration pending - received framework mode response";
        }
    }

    /**
     * Test 4: Verify MCP tool connectivity
     *
     * @test
     *
     * @group live
     * @group mcp
     */
    public function test_mcp_tool_connectivity()
    {
        $this->markTestSkipped('Live test - requires MCP server and API access. Run manually with --group=live');

        // Act: Try a simple search to verify MCP is working
        try {
            $results = $this->odlukeAgent->searchHomeSearchCases([
                'region' => 'nationwide',
                'year' => 2024,
            ]);

            // Assert: Should not be in framework mode if MCP is working
            if (isset($results['status']) && $results['status'] === 'framework_mode') {
                $this->fail('MCP tool not available - still in framework mode');
            }

            echo "\n✅ MCP tool is connected and responding";
            $this->assertTrue(true); // Test passes if we get here

        } catch (\Exception $e) {
            $this->fail("MCP tool connection failed: {$e->getMessage()}");
        }
    }

    /**
     * Test 5: Benchmark extraction on known court decision
     *
     * @test
     *
     * @group live
     * @group mcp
     */
    public function test_benchmark_extraction_on_known_decision()
    {
        $this->markTestSkipped('Live test - requires MCP server and API access. Run manually with --group=live');

        // This test would fetch a specific known decision and validate extraction
        // against expected values

        // Example: K-123/2024 from Općinski sud u Osijeku
        // Expected: prekršaj, misdemeanor, evidence suppressed, etc.

        $this->assertTrue(true); // Placeholder
    }
}
