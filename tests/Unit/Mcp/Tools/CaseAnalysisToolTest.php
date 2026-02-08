<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\CaseAnalysisTool;
use App\Services\CaseSearchService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for CaseAnalysisTool
 *
 * Tests case analysis functionality including:
 * - Case strength assessment
 * - Risk analysis
 * - Timeline reconstruction
 * - Evidence evaluation
 *
 * Sprint 12.5 - Worker B: Missing MCP Tools
 */
class CaseAnalysisToolTest extends TestCase
{
    protected $caseSearchService;

    protected CaseAnalysisTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseSearchService = Mockery::mock(CaseSearchService::class);
        $this->tool = new CaseAnalysisTool($this->caseSearchService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Test 1: Analyze case strength
    // ========================================

    public function test_analyzes_case_strength(): void
    {
        // Arrange: Mock service to return case analysis
        $this->caseSearchService
            ->shouldReceive('analyzeCase')
            ->once()
            ->with('case-123', Mockery::any())
            ->andReturn([
                'case_id' => 'case-123',
                'strength_score' => 75,
                'strengths' => ['Strong evidence', 'Credible witnesses'],
                'weaknesses' => ['Missing documentation'],
                'overall_assessment' => 'Moderate to strong case',
            ]);

        // Act: Call tool
        $request = new Request(['case_id' => 'case-123', 'analysis_type' => 'strength']);
        $result = $this->tool->handle($request);

        // Assert: Result contains analysis
        $this->assertInstanceOf(Response::class, $result);
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('case-123', $output['case_id']);
        $this->assertEquals(75, $output['strength_score']);
        $this->assertIsArray($output['strengths']);
        $this->assertIsArray($output['weaknesses']);
    }

    // ========================================
    // Test 2: Analyze case risks
    // ========================================

    public function test_analyzes_case_risks(): void
    {
        // Arrange: Mock service to return risk analysis
        $this->caseSearchService
            ->shouldReceive('analyzeCase')
            ->once()
            ->with('case-456', Mockery::any())
            ->andReturn([
                'case_id' => 'case-456',
                'risk_level' => 'high',
                'risks' => [
                    ['type' => 'procedural', 'description' => 'Statute of limitations approaching', 'severity' => 'high'],
                    ['type' => 'evidential', 'description' => 'Witness credibility issues', 'severity' => 'medium'],
                ],
                'mitigation_strategies' => ['File motion immediately', 'Prepare witness coaching'],
            ]);

        // Act: Call tool
        $request = new Request(['case_id' => 'case-456', 'analysis_type' => 'risk']);
        $result = $this->tool->handle($request);

        // Assert: Result contains risk analysis
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('case-456', $output['case_id']);
        $this->assertEquals('high', $output['risk_level']);
        $this->assertCount(2, $output['risks']);
        $this->assertIsArray($output['mitigation_strategies']);
    }

    // ========================================
    // Test 3: Reconstruct case timeline
    // ========================================

    public function test_reconstructs_case_timeline(): void
    {
        // Arrange: Mock service to return timeline
        $this->caseSearchService
            ->shouldReceive('analyzeCase')
            ->once()
            ->with('case-789', Mockery::any())
            ->andReturn([
                'case_id' => 'case-789',
                'events' => [
                    ['date' => '2024-01-01', 'event' => 'Incident occurred', 'source' => 'Police report'],
                    ['date' => '2024-01-15', 'event' => 'Client retained', 'source' => 'Engagement letter'],
                    ['date' => '2024-02-01', 'event' => 'Complaint filed', 'source' => 'Court records'],
                ],
                'gaps' => ['Missing documentation between Jan 1-15'],
                'key_dates' => ['2024-01-01', '2024-02-01'],
            ]);

        // Act: Call tool
        $request = new Request(['case_id' => 'case-789', 'analysis_type' => 'timeline']);
        $result = $this->tool->handle($request);

        // Assert: Result contains timeline
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('case-789', $output['case_id']);
        $this->assertCount(3, $output['events']);
        $this->assertIsArray($output['gaps']);
        $this->assertIsArray($output['key_dates']);
    }

    // ========================================
    // Test 4: Evaluate case evidence
    // ========================================

    public function test_evaluates_case_evidence(): void
    {
        // Arrange: Mock service to return evidence evaluation
        $this->caseSearchService
            ->shouldReceive('analyzeCase')
            ->once()
            ->with('case-abc', Mockery::any())
            ->andReturn([
                'case_id' => 'case-abc',
                'evidence_count' => 15,
                'evidence_quality' => 'strong',
                'categories' => [
                    'documentary' => 10,
                    'testimonial' => 3,
                    'physical' => 2,
                ],
                'admissibility_issues' => ['Evidence #7 may violate chain of custody'],
                'recommendations' => ['Obtain expert testimony for physical evidence'],
            ]);

        // Act: Call tool
        $request = new Request(['case_id' => 'case-abc', 'analysis_type' => 'evidence']);
        $result = $this->tool->handle($request);

        // Assert: Result contains evidence evaluation
        $output = json_decode((string) $result->content(), true);
        $this->assertEquals('case-abc', $output['case_id']);
        $this->assertEquals(15, $output['evidence_count']);
        $this->assertEquals('strong', $output['evidence_quality']);
        $this->assertIsArray($output['categories']);
        $this->assertIsArray($output['admissibility_issues']);
    }
}
