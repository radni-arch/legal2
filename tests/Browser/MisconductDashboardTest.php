<?php

namespace Tests\Browser;

use App\Models\CaseDocument;
use Illuminate\Support\Facades\Http;
use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\MocksExternalApis;
use Tests\DuskTestCase;
use Tests\UsesTestDatabase;

/**
 * Misconduct Dashboard Browser Tests
 *
 * Tests the Prosecutorial Misconduct Detection Dashboard for analyzing
 * cases, detecting misconduct patterns, generating dismissal motions,
 * and filing ethics complaints.
 *
 * @group dusk
 * @group misconduct
 */
class MisconductDashboardTest extends DuskTestCase
{
    use MocksExternalApis, UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAllExternalApis();
    }

    /**
     * Test misconduct detection dashboard loads and displays cases
     *
     * Verifies:
     * - Dashboard page loads
     * - Case list is displayed
     * - Filtering works
     * - Sorting works
     * - Statistics are shown
     */
    public function test_misconduct_dashboard_loads_and_displays_cases(): void
    {
        // Create test cases with various misconduct indicators
        CaseDocument::factory()->count(5)->create([
            'content' => 'Prosecution failed to disclose exculpatory evidence',
            'metadata' => json_encode(['misconduct_score' => 85]),
        ]);

        CaseDocument::factory()->count(3)->create([
            'content' => 'Proper procedure followed throughout',
            'metadata' => json_encode(['misconduct_score' => 15]),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/misconduct-dashboard')
                ->assertSee('Prosecutorial Misconduct Detection')

                    // Verify statistics panel
                ->assertPresent('#statistics-panel')
                ->assertSee('Total Cases Analyzed')
                ->assertSee('High Risk Cases')
                ->assertSee('Misconduct Types Detected')

                    // Verify case list table
                ->assertPresent('#cases-table')
                ->assertPresent('table')

                    // Verify at least some cases are shown
                ->assertSeeIn('#cases-table', 'exculpatory evidence')

                    // Test filtering by risk level
                ->select('risk_filter', 'high')
                ->press('Filter')
                ->waitFor('.case-row[data-risk="high"]', 15)
                ->assertDontSee('Proper procedure')

                    // Reset filter
                ->select('risk_filter', 'all')
                ->press('Filter')

                    // Test sorting by misconduct score
                ->click('th[data-sort="misconduct_score"]')
                ->pause(1000)
                ->assertPresent('.sorted-desc');
        });
    }

    /**
     * Test misconduct type detection across 6 categories
     *
     * Verifies:
     * - All 6 misconduct types are detectable
     * - Detection results are accurate
     * - Severity scores are calculated
     * - Legal citations are provided
     */
    public function test_misconduct_type_detection(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'misconduct_types' => [
                                [
                                    'type' => 'brady_violation',
                                    'detected' => true,
                                    'confidence' => 0.92,
                                    'description' => 'Failure to disclose exculpatory evidence',
                                    'legal_basis' => ['Brady v. Maryland', 'ZKP Članak 63'],
                                ],
                                [
                                    'type' => 'witness_coaching',
                                    'detected' => true,
                                    'confidence' => 0.78,
                                    'description' => 'Inappropriate witness preparation',
                                    'legal_basis' => ['ZKP Članak 291'],
                                ],
                            ],
                            'severity_score' => 87,
                            'recommended_action' => 'File motion to dismiss',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $caseDoc = CaseDocument::factory()->create([
            'content' => 'Prosecutor withheld forensic report favorable to defendant. Witness testimony appears coached.',
        ]);

        $this->browse(function (Browser $browser) use ($caseDoc) {
            $browser->visit('/misconduct-dashboard')

                    // Click analyze button for specific case
                ->click('[data-analyze-case="'.$caseDoc->id.'"]')

                    // Wait for analysis modal
                ->waitFor('#analysis-modal', 15)

                    // Verify all 6 misconduct types are listed
                ->assertSee('Brady Violation')
                ->assertSee('Witness Coaching')
                ->assertSee('Evidence Fabrication')
                ->assertSee('Improper Argument')
                ->assertSee('Vindictive Prosecution')
                ->assertSee('Discovery Abuse')

                    // Verify detected types are highlighted
                ->assertPresent('[data-type="brady_violation"][data-detected="true"]')
                ->assertPresent('[data-type="witness_coaching"][data-detected="true"]')

                    // Verify confidence scores are shown
                ->assertSee('92%')
                ->assertSee('78%')

                    // Verify legal citations
                ->assertSee('Brady v. Maryland')
                ->assertSee('ZKP Članak 63')

                    // Verify severity score
                ->assertSee('Severity Score: 87')
                ->assertPresent('.severity-meter')

                    // Verify recommended action
                ->assertSee('File motion to dismiss');
        });
    }

    /**
     * Test dismissal motion generation from misconduct analysis
     *
     * Verifies:
     * - Dismissal motion can be generated
     * - Motion includes all detected misconduct
     * - Proper Croatian legal format
     * - Citations are complete
     * - Motion can be edited and exported
     */
    public function test_dismissal_motion_generation(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "PRIJEDLOG ZA ODBACIVANJE OPTUŽNICE\n\n".
                                   "Temeljem članka 184. Zakona o kaznenom postupku Republike Hrvatske...\n\n".
                                   "I. ČINJENIČNO STANJE\n\n".
                                   '1. Državno odvjetništvo povrijedilo je temeljno načelo...',
                    ],
                ]],
            ]),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/misconduct-dashboard')

                    // Select case with detected misconduct
                ->check('cases[]', 1)

                    // Click generate dismissal motion
                ->press('Generate Dismissal Motion')

                    // Wait for motion generator
                ->waitFor('#motion-generator-modal', 15)

                    // Verify motion includes detected types
                ->assertSee('Brady Violation')
                ->assertSee('Witness Coaching')

                    // Select additional grounds
                ->check('include_due_process_violation')
                ->check('include_ethical_violations')

                    // Add custom arguments
                ->type('custom_arguments', 'Cumulative effect of violations denies fair trial')

                    // Generate motion
                ->press('Generate Motion')

                    // Wait for generation
                ->waitForText('PRIJEDLOG ZA ODBACIVANJE', 20)

                    // Verify motion structure
                ->assertSee('Zakona o kaznenom postupku')
                ->assertSee('ČINJENIČNO STANJE')
                ->assertSee('povrijedilo')

                    // Verify citations
                ->assertPresent('.legal-citation')

                    // Edit motion
                ->click('#edit-dismissal-motion')
                ->waitFor('#motion-editor', 15)

                    // Add additional paragraph
                ->keys('#motion-editor', ['{end}'])
                ->type('#motion-editor', '\n\nDodatna argumentacija: Kumulativni učinak povreda.')

                    // Save motion
                ->press('Save Motion')
                ->waitForText('Motion saved', 15)

                    // Preview motion
                ->press('Preview')
                ->waitFor('#motion-preview', 15)
                ->assertSee('Dodatna argumentacija')

                    // Export as PDF
                ->press('Export PDF')
                ->waitForText('PDF generated', 20);
        });
    }

    /**
     * Test ethics complaint generation
     *
     * Verifies:
     * - Ethics complaint form works
     * - Complaint includes all violations
     * - Proper format for bar association
     * - Supporting documentation can be attached
     * - Complaint can be filed electronically
     */
    public function test_ethics_complaint_generation(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "PRITUŽBA ODVJETNIČKOJ KOMORI\n\n".
                                   "Poštovani,\n\n".
                                   'Ovim putem podnosim pritužbu protiv državnog odvjetnika...',
                    ],
                ]],
            ]),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/misconduct-dashboard')

                    // Select case for complaint
                ->click('[data-case-id="1"]')

                    // Click ethics complaint button
                ->press('File Ethics Complaint')

                    // Wait for complaint form
                ->waitFor('#ethics-complaint-form', 15)

                    // Fill prosecutor information
                ->type('prosecutor_name', 'Ivana Horvat')
                ->type('prosecutor_office', 'Županijsko državno odvjetništvo u Osijeku')

                    // Select violations
                ->check('violation_types[]', 'brady')
                ->check('violation_types[]', 'false_statements')

                    // Add narrative
                ->type('complaint_narrative', 'Državni odvjetnik namjerno prikrio dokaze koji bi oslobodili optuženog.')

                    // Attach supporting documents
                ->attach('supporting_docs[]', __DIR__.'/fixtures/test.pdf')

                    // Generate complaint
                ->press('Generate Complaint')

                    // Wait for generation
                ->waitForText('PRITUŽBA ODVJETNIČKOJ KOMORI', 20)

                    // Verify complaint structure
                ->assertSee('Poštovani')
                ->assertSee('državnog odvjetnika')
                ->assertSee('Ivana Horvat')

                    // Verify violations are described
                ->assertSee('prikrio dokaze')

                    // Review and edit
                ->press('Edit Complaint')
                ->type('#complaint-editor', 'Additional violation details...')
                ->press('Save')

                    // Submit electronically to bar
                ->press('Submit to Bar Association')

                    // Confirm submission
                ->waitFor('#confirm-submission-modal', 15)
                ->assertSee('This will electronically file the complaint')
                ->press('Confirm Submission')

                    // Verify success
                ->waitForText('Complaint filed successfully', 20)
                ->assertSee('Tracking Number');
        });
    }

    /**
     * Test misconduct pattern analysis across multiple cases
     *
     * Verifies:
     * - Pattern detection works
     * - Statistics are calculated correctly
     * - Prosecutor profiles are generated
     * - Trends are visualized
     * - Reports can be exported
     */
    public function test_misconduct_pattern_analysis(): void
    {
        // Create multiple cases from same prosecutor
        for ($i = 0; $i < 10; $i++) {
            CaseDocument::factory()->create([
                'content' => 'Brady violation detected in Case '.$i,
                'metadata' => json_encode([
                    'prosecutor' => 'Marko Kovač',
                    'misconduct_types' => ['brady_violation'],
                ]),
            ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->visit('/misconduct-dashboard')

                    // Navigate to pattern analysis
                ->click('#pattern-analysis-tab')
                ->waitFor('#pattern-analysis-panel', 15)

                    // Select analysis scope
                ->select('analysis_scope', 'by_prosecutor')
                ->select('time_period', 'last_year')

                    // Run analysis
                ->press('Analyze Patterns')

                    // Wait for analysis
                ->waitForText('Pattern Analysis Results', 20)

                    // Verify prosecutor is identified
                ->assertSee('Marko Kovač')
                ->assertSee('10 cases')

                    // Verify misconduct type distribution
                ->assertPresent('#misconduct-distribution-chart')
                ->assertSee('Brady Violation: 100%')

                    // View prosecutor profile
                ->click('[data-prosecutor="Marko Kovač"]')

                    // Wait for profile modal
                ->waitFor('#prosecutor-profile-modal', 15)

                    // Verify profile details
                ->assertSee('Cases Filed: 10')
                ->assertSee('Misconduct Rate')
                ->assertSee('Most Common Violation: Brady')

                    // Verify trend chart
                ->assertPresent('#trend-chart')

                    // Close profile
                ->press('Close')

                    // Export pattern report
                ->press('Export Pattern Report')
                ->waitForText('Report generated', 20)
                ->assertSee('Download');
        });
    }
}
