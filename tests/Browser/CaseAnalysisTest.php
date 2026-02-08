<?php

namespace Tests\Browser;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\AuthenticatesUser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;

/**
 * E2E Browser Tests for Case Analysis Feature
 *
 * Tests the full case analysis functionality including:
 * - Case strength analysis (uses OpenAI)
 * - Risk assessment
 * - Timeline visualization
 * - Evidence quality assessment
 * - Combined analysis workflows
 */
class CaseAnalysisTest extends DuskTestCase
{
    use AuthenticatesUser, DatabaseMigrations, MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test 1: Case Strength Analysis Display
     *
     * Verifies that the case strength analysis UI displays:
     * - Strength score (0-100)
     * - List of case strengths
     * - List of case weaknesses
     * - Overall assessment text
     *
     * @test
     */
    public function test_case_strength_analysis_display(): void
    {
        // Mock OpenAI API for strength analysis
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'strength_score' => 78,
                            'strengths' => [
                                'Strong documentary evidence',
                                'Credible witness testimony',
                                'Clear procedural violations by prosecution',
                            ],
                            'weaknesses' => [
                                'Limited forensic evidence',
                                'Client has prior record',
                            ],
                            'overall_assessment' => 'Moderate to strong case with good prospects for favorable outcome. Constitutional violations provide strong defense angle.',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(5))
            ->create([
                'case_number' => 'Pp-123/2025',
                'client_name' => 'Ivan Horvat',
                'opponent_name' => 'Državno odvjetništvo',
                'court' => 'Županijski sud u Osijeku',
                'status' => 'Active',
            ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Navigate to Case Analysis module
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)

                // Select case
                ->select('@case-selector', $case->id)
                ->pause(500)

                // Select strength analysis type
                ->select('@analysis-type', 'strength')

                // Click analyze button
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Verify strength score displayed
                ->assertSee('Strength Score')
                ->assertSee('78')

                // Verify strengths list
                ->assertSee('Case Strengths')
                ->assertSee('Strong documentary evidence')
                ->assertSee('Credible witness testimony')
                ->assertSee('Constitutional violations')

                // Verify weaknesses list
                ->assertSee('Case Weaknesses')
                ->assertSee('Limited forensic evidence')
                ->assertSee('prior record')

                // Verify overall assessment
                ->assertSee('Overall Assessment')
                ->assertSee('Moderate to strong case')
                ->assertSee('favorable outcome');
        });
    }

    /**
     * Test 2: Risk Assessment UI
     *
     * Verifies that the risk assessment UI displays:
     * - Risk level (low/medium/high)
     * - Identified risks
     * - Mitigation strategies
     *
     * @test
     */
    public function test_risk_assessment_ui(): void
    {
        $user = User::factory()->create();

        // Create case with specific risk factors
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(2)) // Limited docs = risk
            ->create([
                'case_number' => 'Pp-456/2025',
                'client_name' => 'Ana Marić',
                'status' => 'Pending',
                'filing_date' => now()->subMonths(7), // Old case = risk
            ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Navigate to Case Analysis
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)

                // Select case
                ->select('@case-selector', $case->id)

                // Select risk analysis type
                ->select('@analysis-type', 'risk')

                // Analyze
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Verify risk level displayed
                ->assertSee('Risk Level')
                ->assertSeeIn('@risk-level', 'medium')

                // Verify identified risks
                ->assertSee('Identified Risks')
                ->assertSee('pending for over 6 months')
                ->assertSee('Limited documentation')

                // Verify mitigation strategies
                ->assertSee('Mitigation Strategies')
                ->assertSee('Schedule status conference')
                ->assertSee('Request additional documents');
        });
    }

    /**
     * Test 3: Timeline Visualization
     *
     * Verifies that the timeline analysis UI displays:
     * - Chronological list of events
     * - Event types (filing, document, hearing)
     * - Key dates highlighted
     * - Timeline gaps identified
     *
     * @test
     */
    public function test_timeline_visualization(): void
    {
        $user = User::factory()->create();

        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-789/2025',
            'filing_date' => now()->subMonths(3),
        ]);

        // Add documents with different timestamps
        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Initial Complaint',
            'created_at' => now()->subMonths(3),
        ]);

        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Defense Response',
            'created_at' => now()->subMonths(2),
        ]);

        CaseDocument::factory()->create([
            'case_id' => $case->id,
            'title' => 'Expert Testimony',
            'created_at' => now()->subWeeks(2),
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Navigate to Case Analysis
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)

                // Select case
                ->select('@case-selector', $case->id)

                // Select timeline analysis
                ->select('@analysis-type', 'timeline')

                // Analyze
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Verify timeline header
                ->assertSee('Case Timeline')
                ->assertSee('Events')

                // Verify filing event
                ->assertSee('Case filed')
                ->assertSee('filing')

                // Verify document events
                ->assertSee('Document added: Initial Complaint')
                ->assertSee('Document added: Defense Response')
                ->assertSee('Document added: Expert Testimony')
                ->assertSee('document')

                // Verify key dates section
                ->assertSee('Key Dates')

                // Verify events are chronologically ordered
                ->assertPresent('@timeline-events');
        });
    }

    /**
     * Test 4: Evidence Quality Assessment
     *
     * Verifies that the evidence analysis UI displays:
     * - Evidence count
     * - Evidence quality rating
     * - Categories of evidence
     * - Admissibility issues (if any)
     * - Recommendations
     *
     * @test
     */
    public function test_evidence_quality_assessment(): void
    {
        $user = User::factory()->create();

        $case = LegalCase::factory()->create([
            'case_number' => 'Pp-999/2025',
        ]);

        // Add evidence documents
        CaseDocument::factory()->count(6)->create([
            'case_id' => $case->id,
            'category' => 'evidence',
        ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)

                // Navigate to Case Analysis
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)

                // Select case
                ->select('@case-selector', $case->id)

                // Select evidence analysis
                ->select('@analysis-type', 'evidence')

                // Analyze
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Verify evidence count
                ->assertSee('Evidence Count')
                ->assertSee('6')

                // Verify evidence quality
                ->assertSee('Evidence Quality')
                ->assertSeeIn('@evidence-quality', 'good')

                // Verify categories section
                ->assertSee('Evidence Categories')
                ->assertSee('evidence')

                // Verify admissibility section
                ->assertSee('Admissibility Issues')

                // Verify recommendations
                ->assertSee('Recommendations')
                ->assertSee('Prepare exhibits for trial');
        });
    }

    /**
     * Test 5: All Analysis Types Work Together
     *
     * Comprehensive workflow test running all 4 analysis types
     * sequentially to verify they all work correctly and don't
     * interfere with each other.
     *
     * @test
     */
    public function test_all_analysis_types_work_together(): void
    {
        // Mock OpenAI for strength analysis
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'strength_score' => 82,
                            'strengths' => ['Strong evidence', 'Good legal precedent'],
                            'weaknesses' => ['Minor procedural issue'],
                            'overall_assessment' => 'Strong case overall',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(5)->state(['category' => 'evidence']))
            ->create([
                'case_number' => 'Pp-COMPLETE/2025',
                'client_name' => 'Marko Horvat',
                'filing_date' => now()->subMonths(2),
                'status' => 'Active',
            ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)
                ->select('@case-selector', $case->id)
                ->pause(1000);

            // Test 1: Strength Analysis
            $browser->select('@analysis-type', 'strength')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)
                ->assertSee('Strength Score')
                ->assertSee('82')
                ->assertSee('Strong case overall');

            // Clear results
            $browser->press('Clear Results')
                ->pause(1000);

            // Test 2: Risk Analysis
            $browser->select('@analysis-type', 'risk')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)
                ->assertSee('Risk Level')
                ->assertSee('Mitigation Strategies');

            // Clear results
            $browser->press('Clear Results')
                ->pause(1000);

            // Test 3: Timeline Analysis
            $browser->select('@analysis-type', 'timeline')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)
                ->assertSee('Case Timeline')
                ->assertSee('Events')
                ->assertSee('Case filed');

            // Clear results
            $browser->press('Clear Results')
                ->pause(1000);

            // Test 4: Evidence Analysis
            $browser->select('@analysis-type', 'evidence')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)
                ->assertSee('Evidence Count')
                ->assertSee('Evidence Quality')
                ->assertSee('5');
        });
    }

    /**
     * Test 6: Case Analysis With Missing Case
     *
     * Verifies proper error handling when analyzing non-existent case
     *
     * @test
     */
    public function test_case_analysis_with_missing_case(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)

                // Try to analyze without selecting a case
                ->select('@analysis-type', 'strength')
                ->press('Analyze Case')
                ->waitForText('Error', 20)
                ->assertSee('Please select a case')
                ->assertDontSee('Analysis Complete');
        });
    }

    /**
     * Test 7: Strength Analysis With Minimal Documentation
     *
     * Tests strength analysis when case has very few documents
     *
     * @test
     */
    public function test_strength_analysis_with_minimal_documentation(): void
    {
        // Mock OpenAI response for case with weak documentation
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'strength_score' => 42,
                            'strengths' => ['Client cooperation'],
                            'weaknesses' => [
                                'Insufficient documentation',
                                'Limited evidence',
                                'Weak witness statements',
                            ],
                            'overall_assessment' => 'Case requires significant strengthening. Immediate action needed to gather additional evidence.',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Case with only 1 document
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(1))
            ->create([
                'case_number' => 'Pp-WEAK/2025',
                'status' => 'Pending',
            ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)
                ->select('@case-selector', $case->id)
                ->select('@analysis-type', 'strength')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Verify low score
                ->assertSee('42')

                // Verify weaknesses prominently displayed
                ->assertSee('Insufficient documentation')
                ->assertSee('Limited evidence')

                // Verify actionable assessment
                ->assertSee('requires significant strengthening')
                ->assertSee('Immediate action needed');
        });
    }

    /**
     * Test 8: Export Analysis Results
     *
     * Verifies that analysis results can be exported to PDF
     *
     * @test
     */
    public function test_export_analysis_results(): void
    {
        // Mock OpenAI
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'strength_score' => 75,
                            'strengths' => ['Good evidence'],
                            'weaknesses' => ['Minor issues'],
                            'overall_assessment' => 'Solid case',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $case = LegalCase::factory()
            ->has(CaseDocument::factory()->count(3))
            ->create([
                'case_number' => 'Pp-EXPORT/2025',
            ]);

        $this->browse(function (Browser $browser) use ($user, $case) {
            $this->loginAs($browser, $user);

            $browser->visit('/playground')
                ->waitForText('Legal Defense Playground', 20)
                ->click('@case-analysis-tab')
                ->waitFor('@case-analysis-panel', 20)
                ->select('@case-selector', $case->id)
                ->select('@analysis-type', 'strength')
                ->press('Analyze Case')
                ->waitForText('Analysis Complete', 60)

                // Export results
                ->press('Export to PDF')
                ->pause(3000)
                ->assertSee('Generating PDF');
        });
    }
}
