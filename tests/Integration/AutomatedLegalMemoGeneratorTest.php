<?php

namespace Tests\Integration;

use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\Documents\AutomatedLegalMemoGenerator;

/**
 * Integration tests for Automated Legal Memo Generator
 *
 * Tests the complete memo generation workflow including IRAC analysis,
 * precedent integration, and multi-format export capabilities.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch) are mocked via IntegrationTestCase
 */
class AutomatedLegalMemoGeneratorTest extends IntegrationTestCase
{
    protected User $user;

    protected AutomatedLegalMemoGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->generator = app(AutomatedLegalMemoGenerator::class);
    }

    /** @test */
    public function it_generates_complete_legal_memo()
    {
        // External services (OpenAI, DecisionSearch) are already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'ABC Corp', 'role' => 'plaintiff', 'type' => 'corporation'],
                    ['name' => 'XYZ Inc', 'role' => 'defendant', 'type' => 'corporation'],
                ],
                'legal_issues' => [
                    [
                        'issue' => 'Whether defendant breached the contract',
                        'area_of_law' => 'contract',
                        'elements' => ['Valid contract', 'Breach', 'Damages'],
                    ],
                ],
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-15'],
                    ['description' => 'Payment due', 'date' => '2024-02-15'],
                    ['description' => 'Defendant failed to pay', 'date' => '2024-02-16'],
                ],
                'evidence' => [
                    ['type' => 'documentary', 'description' => 'Signed contract'],
                    ['type' => 'documentary', 'description' => 'Payment records'],
                ],
                'facts_favorable_to_plaintiff' => [
                    'Clear contract terms',
                    'Documented breach',
                ],
                'facts_favorable_to_defendant' => [
                    'Claimed force majeure',
                ],
                'disputed_facts' => [
                    'Whether force majeure clause applies',
                ],
                'damages_or_relief_sought' => [
                    'type' => 'monetary',
                    'amount' => '500,000 HRK',
                    'description' => 'Contract damages',
                ],
                'summary' => 'Contract breach with force majeure defense',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        // Verify memo structure (service returns array, not model)
        $this->assertIsArray($memo);

        // Verify memo sections exist
        $this->assertArrayHasKey('header', $memo);
        $this->assertArrayHasKey('issue', $memo);
        $this->assertArrayHasKey('brief_answer', $memo);
        $this->assertArrayHasKey('facts', $memo);
        $this->assertArrayHasKey('analysis', $memo);
        $this->assertArrayHasKey('conclusion', $memo);
        $this->assertArrayHasKey('recommendations', $memo);
        $this->assertArrayHasKey('full_text', $memo);

        // Verify header contains required information
        $header = $memo['header'];
        $this->assertIsArray($header);
        $this->assertArrayHasKey('to', $header);
        $this->assertArrayHasKey('from', $header);
        $this->assertArrayHasKey('date', $header);
        $this->assertArrayHasKey('regarding', $header);

        // Verify analysis uses IRAC format
        $analysis = $memo['analysis'];
        $this->assertIsArray($analysis);
        $this->assertNotEmpty($analysis);

        foreach ($analysis as $issueAnalysis) {
            $this->assertArrayHasKey('issue', $issueAnalysis);
            $this->assertArrayHasKey('rule', $issueAnalysis);
            $this->assertArrayHasKey('application', $issueAnalysis);
            $this->assertArrayHasKey('conclusion', $issueAnalysis);
        }

        // Verify recommendations provided
        $recommendations = $memo['recommendations'];
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
    }

    /** @test */
    public function it_integrates_precedents_into_analysis()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Breach of contract', 'area_of_law' => 'contract'],
                ],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        // Should have precedents in metadata
        $this->assertNotEmpty($memo['metadata']['precedents_used'] ?? []);
        $this->assertGreaterThanOrEqual(2, count($memo['metadata']['precedents_used']));

        // Analysis should reference precedents
        $analysis = $memo['analysis'];
        $hasRuleSection = collect($analysis)->contains(function ($item) {
            return ! empty($item['rule']);
        });
        $this->assertTrue($hasRuleSection);
    }

    /** @test */
    public function it_generates_issue_statement()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    [
                        'issue' => 'Whether employer wrongfully terminated employee',
                        'area_of_law' => 'employment',
                    ],
                ],
                'parties' => [
                    ['name' => 'Employee', 'role' => 'plaintiff'],
                    ['name' => 'Employer Corp', 'role' => 'defendant'],
                ],
                'events' => [],
                'evidence' => [],
                'summary' => 'Wrongful termination case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $issue = $memo['issue'];

        $this->assertNotEmpty($issue);
        $this->assertIsString($issue);
        $this->assertStringContainsString('Whether', $issue);
    }

    /** @test */
    public function it_provides_brief_answer()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Liability for negligence'],
                ],
                'facts_favorable_to_plaintiff' => [
                    'Strong evidence of negligence',
                    'Clear causation',
                ],
                'facts_favorable_to_defendant' => [],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Strong liability case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $briefAnswer = $memo['brief_answer'];

        $this->assertNotEmpty($briefAnswer);
        $this->assertIsString($briefAnswer);
        // Brief answer should be concise (typically 1-3 sentences)
        $this->assertLessThan(500, strlen($briefAnswer));
    }

    /** @test */
    public function it_summarizes_facts_chronologically()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-15'],
                    ['description' => 'First payment made', 'date' => '2024-02-01'],
                    ['description' => 'Dispute arose', 'date' => '2024-03-10'],
                    ['description' => 'Notice sent', 'date' => '2024-03-15'],
                ],
                'parties' => [
                    ['name' => 'Party A', 'role' => 'plaintiff'],
                    ['name' => 'Party B', 'role' => 'defendant'],
                ],
                'legal_issues' => [],
                'evidence' => [],
                'summary' => 'Timeline case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $facts = $memo['facts'];

        $this->assertNotEmpty($facts);
        $this->assertIsString($facts);
        // Should mention parties
        $this->assertTrue(
            str_contains($facts, 'Party A') || str_contains($facts, 'Party B')
        );
    }

    /** @test */
    public function it_applies_irac_methodology()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    [
                        'issue' => 'Whether contract is enforceable',
                        'area_of_law' => 'contract',
                        'elements' => ['Offer', 'Acceptance', 'Consideration'],
                    ],
                ],
                'facts_favorable_to_plaintiff' => ['Clear offer', 'Documented acceptance'],
                'evidence' => [['type' => 'documentary', 'description' => 'Signed agreement']],
                'parties' => [],
                'events' => [],
                'summary' => 'Contract enforceability',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $analysis = $memo['analysis'];

        $this->assertNotEmpty($analysis);
        $this->assertIsArray($analysis);

        $firstIssue = $analysis[0];

        // Issue: Should state the legal question
        $this->assertNotEmpty($firstIssue['issue']);

        // Rule: Should state the applicable law
        $this->assertNotEmpty($firstIssue['rule']);

        // Application: Should apply facts to law
        $this->assertNotEmpty($firstIssue['application']);

        // Conclusion: Should reach a conclusion
        $this->assertNotEmpty($firstIssue['conclusion']);
    }

    /** @test */
    public function it_generates_actionable_recommendations()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Liability determination'],
                ],
                'evidence' => [
                    ['type' => 'testimonial', 'availability' => 'needs_discovery'],
                ],
                'disputed_facts' => ['Critical disputed fact'],
                'procedural_posture' => [
                    'stage' => 'pre-filing',
                    'deadlines' => [],
                ],
                'parties' => [],
                'events' => [],
                'summary' => 'Pre-filing case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $recommendations = $memo['recommendations'];

        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);

        // Should have specific, actionable items
        foreach ($recommendations as $recommendation) {
            $this->assertNotEmpty($recommendation);
            $this->assertIsString($recommendation);
        }
    }

    /** @test */
    public function it_exports_memo_in_text_format()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test issue']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $textFormat = $this->generator->generateInFormat($factPattern->id, 'text');

        $this->assertNotEmpty($textFormat);
        $this->assertIsString($textFormat);

        // Should contain key sections
        $this->assertStringContainsString('MEMORANDUM', $textFormat);
        $this->assertStringContainsString('ISSUE', $textFormat);
        $this->assertStringContainsString('ANALYSIS', $textFormat);
        $this->assertStringContainsString('CONCLUSION', $textFormat);
    }

    /** @test */
    public function it_exports_memo_in_markdown_format()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test issue']],
                'parties' => [['name' => 'Test Party', 'role' => 'plaintiff']],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $markdownFormat = $this->generator->generateInFormat($factPattern->id, 'markdown');

        $this->assertNotEmpty($markdownFormat);
        $this->assertIsString($markdownFormat);

        // Should use markdown formatting
        $this->assertStringContainsString('#', $markdownFormat); // Headers
        $this->assertStringContainsString('**', $markdownFormat); // Bold
    }

    /** @test */
    public function it_exports_memo_in_html_format()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test issue']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $htmlFormat = $this->generator->generateInFormat($factPattern->id, 'html');

        $this->assertNotEmpty($htmlFormat);
        $this->assertIsString($htmlFormat);

        // Should be valid HTML
        $this->assertStringContainsString('<html', $htmlFormat);
        $this->assertStringContainsString('<h1>', $htmlFormat);
        $this->assertStringContainsString('</html>', $htmlFormat);
    }

    /** @test */
    public function it_handles_multiple_legal_issues()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [
                    ['issue' => 'Issue 1: Contract formation', 'area_of_law' => 'contract'],
                    ['issue' => 'Issue 2: Breach', 'area_of_law' => 'contract'],
                    ['issue' => 'Issue 3: Damages', 'area_of_law' => 'contract'],
                ],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Multi-issue case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        $analysis = $memo['analysis'];

        // Should have IRAC for each issue
        $this->assertCount(3, $analysis);

        foreach ($analysis as $issueAnalysis) {
            $this->assertArrayHasKey('issue', $issueAnalysis);
            $this->assertArrayHasKey('rule', $issueAnalysis);
            $this->assertArrayHasKey('application', $issueAnalysis);
            $this->assertArrayHasKey('conclusion', $issueAnalysis);
        }
    }

    /** @test */
    public function it_stores_generation_metadata()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Test']],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id, [
            'recipient' => 'Senior Partner',
            'purpose' => 'Case evaluation',
        ]);

        $metadata = $memo['metadata'];

        $this->assertArrayHasKey('precedents_used', $metadata);
        $this->assertArrayHasKey('generation_options', $metadata);
        $this->assertArrayHasKey('generated_at', $metadata);

        // Should store custom options
        $this->assertEquals('Senior Partner', $metadata['generation_options']['recipient']);
        $this->assertEquals('Case evaluation', $metadata['generation_options']['purpose']);
    }

    /**
     * @test
     *
     * @group skip
     *
     * SKIPPED: Service currently returns arrays, doesn't persist to database.
     * LegalMemo model doesn't exist yet. This test should be implemented
     * when memo persistence functionality is added.
     */
    public function it_can_retrieve_memos_for_fact_pattern()
    {
        $this->markTestSkipped('Memo persistence not yet implemented - service returns arrays only');
    }

    /** @test */
    public function it_includes_case_strengths_and_weaknesses()
    {
        // DecisionSearchService already mocked by IntegrationTestCase

        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'legal_issues' => [['issue' => 'Liability']],
                'facts_favorable_to_plaintiff' => [
                    'Strong documentary evidence',
                    'Clear breach',
                ],
                'facts_favorable_to_defendant' => [
                    'Statute of limitations issue',
                ],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Mixed strength case',
            ],
        ]);

        $memo = $this->generator->generateMemo($factPattern->id);

        // Analysis should address both strengths and weaknesses
        $analysis = $memo['analysis'];
        $this->assertNotEmpty($analysis);

        // Application section should discuss favorable and unfavorable facts
        $hasApplicationDiscussion = collect($analysis)->contains(function ($item) {
            return ! empty($item['application']) && strlen($item['application']) > 50;
        });
        $this->assertTrue($hasApplicationDiscussion);
    }
}
