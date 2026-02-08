<?php

namespace Tests\Unit;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Modules\Misconduct\Services\MisconductDetector;
use App\Modules\Misconduct\Services\MisconductPatternAnalyzer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class MisconductDetectorTest extends TestCase
{
    use UsesTestDatabase;

    protected LegalCase $testCase;

    protected MisconductDetector $detector;

    protected MisconductPatternAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $this->testCase = LegalCase::factory()->create([
            'title' => 'Test Criminal Case - Misconduct Detection',
            'description' => 'Testing prosecutorial misconduct detection',
            'case_type' => 'criminal',
            'status' => 'active',
        ]);

        $this->detector = app(MisconductDetector::class);
        $this->analyzer = app(MisconductPatternAnalyzer::class);
    }

    /** @test */
    public function test_detects_fabricated_probable_cause()
    {
        // Create warrant document with vague probable cause
        CaseDocument::create([
            'case_id' => $this->testCase->id,
            'title' => 'Search Warrant',
            'category' => 'warrant',
            'content' => 'Based on anonymous tip, defendant was acting suspiciously. I believe crime was committed.',
        ]);

        // Mock OpenAI response indicating fabricated probable cause
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'fabricated' => true,
                                'confidence' => 85,
                                'issues' => [
                                    'Vague description: "acting suspiciously"',
                                    'Uncorroborated anonymous tip',
                                    'Conclusory statement: "I believe crime was committed"',
                                ],
                                'reasoning' => 'Warrant lacks specific, articulable facts establishing probable cause.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($this->testCase);

        $this->assertNotEmpty($violations);
        $this->assertCount(1, $violations);

        $violation = $violations[0];
        $this->assertEquals('fabricated_probable_cause', $violation['type']);
        $this->assertEquals(90, $violation['severity']);
        $this->assertStringContainsString('probable cause', strtolower($violation['description']));
        $this->assertStringContainsString('ZKP', $violation['legal_basis']);
        $this->assertTrue($violation['mandates_dismissal']);
    }

    /** @test */
    public function test_detects_hidden_evidence()
    {
        // Mock OpenAI response indicating Brady violation
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'hidden_evidence_detected' => true,
                                'confidence' => 90,
                                'violations' => [
                                    [
                                        'type' => 'late_disclosure',
                                        'description' => 'Exculpatory witness statement disclosed 2 days before trial',
                                        'evidence_affected' => 'Witness testimony supporting alibi',
                                    ],
                                    [
                                        'type' => 'non_disclosure',
                                        'description' => 'Lab report showing inconsistent fingerprints never disclosed',
                                        'evidence_affected' => 'Forensic evidence',
                                    ],
                                ],
                                'reasoning' => 'Multiple Brady violations detected. Prosecution withheld exculpatory evidence.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($this->testCase);

        $this->assertNotEmpty($violations);
        $this->assertGreaterThanOrEqual(2, count($violations)); // Should detect multiple violations

        $bradyViolations = array_filter($violations, fn ($v) => ($v['type'] ?? '') === 'hidden_evidence');
        $this->assertNotEmpty($bradyViolations);

        $violation = reset($bradyViolations);
        $this->assertEquals('hidden_evidence', $violation['type']);
        $this->assertEquals(95, $violation['severity']);
        $this->assertTrue($violation['mandates_dismissal']);
        $this->assertStringContainsString('Brady', $violation['description']);
    }

    /** @test */
    public function test_detects_backdated_documents()
    {
        // Create document - content suggests backdating
        $document = CaseDocument::create([
            'case_id' => $this->testCase->id,
            'title' => 'Police Report',
            'category' => 'report',
            'content' => 'Incident report filed on '.now()->subDays(5)->format('Y-m-d').'. Report created today.',
        ]);

        // Document content suggests it was created 5 days ago but timestamp shows it was just created
        // This creates the backdating discrepancy

        // Mock OpenAI responses for backdating detection
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'inconsistencies_found' => true,
                                'confidence' => 90,
                                'issues' => [
                                    [
                                        'document' => 'Police Report',
                                        'issue' => 'Document appears to be backdated - timestamp inconsistency detected',
                                        'severity' => 'high',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($this->testCase->fresh());

        $this->assertNotEmpty($violations);

        $backdatedViolations = array_filter($violations, fn ($v) => ($v['type'] ?? '') === 'backdated_document');
        $this->assertNotEmpty($backdatedViolations);

        $violation = reset($backdatedViolations);
        $this->assertEquals('backdated_document', $violation['type']);
        $this->assertEquals(85, $violation['severity']);
        $this->assertStringContainsString('backdat', strtolower($violation['description']));
        // AI-detected violations have 'document' key, not 'document_id'
        $this->assertArrayHasKey('document', $violation['evidence']);
        $this->assertEquals('Police Report', $violation['evidence']['document']);
    }

    /** @test */
    public function test_detects_rights_violations()
    {
        $this->markTestIncomplete('Evidence model not yet implemented');

        // Create evidence of interrogation without lawyer
        // Evidence::create([
        //     'case_id' => $this->testCase->id,
        //     'type' => 'statement',
        //     'description' => 'Defendant statement during interrogation',
        //     'lawyer_present' => false, // No lawyer present
        //     'rights_warned' => false,  // Rights not read
        // ]);

        // Evidence::create([
        //     'case_id' => $this->testCase->id,
        //     'type' => 'statement',
        //     'description' => 'Coerced confession - defendant threatened with maximum sentence',
        //     'lawyer_present' => false,
        // ]);

        // $violations = $this->detector->detect($this->testCase->fresh());

        // $this->assertNotEmpty($violations);

        // $rightsViolations = array_filter($violations, fn($v) => $v['category'] === 'rights_violation');
        // $this->assertGreaterThanOrEqual(1, count($rightsViolations));

        // $violation = reset($rightsViolations);
        // $this->assertEquals('rights_violation', $violation['category']);
        // $this->assertEquals(90, $violation['severity']);
        // $this->assertStringContainsString('Ustav RH', $violation['legal_basis']);
    }

    /** @test */
    public function test_detects_threats_or_lying()
    {
        // Create document with prosecutor threats
        CaseDocument::create([
            'case_id' => $this->testCase->id,
            'title' => 'Interview Transcript',
            'category' => 'transcript',
            'content' => 'Prosecutor told defendant: "If you don\'t confess, I\'ll make sure you get the maximum sentence. We have evidence we don\'t actually have."',
        ]);

        // Mock OpenAI response detecting threats/lying
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'violations_found' => true,
                                'confidence' => 95,
                                'violations' => [
                                    [
                                        'type' => 'threat',
                                        'description' => 'Prosecutor threatened defendant with maximum sentence to coerce confession',
                                        'severity' => 'high',
                                        'quote' => 'If you don\'t confess, I\'ll make sure you get the maximum sentence',
                                    ],
                                    [
                                        'type' => 'lying',
                                        'description' => 'Prosecutor lied about evidence: "We have evidence we don\'t actually have"',
                                        'severity' => 'high',
                                        'quote' => 'We have evidence we don\'t actually have',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($this->testCase->fresh());

        $this->assertNotEmpty($violations);

        $threatViolations = array_filter($violations, fn ($v) => ($v['type'] ?? '') === 'prosecutor_threats_lying');
        $this->assertNotEmpty($threatViolations);

        $violation = reset($threatViolations);
        $this->assertEquals('prosecutor_threats_lying', $violation['type']);
        $this->assertEquals(95, $violation['severity']);
        $this->assertTrue($violation['mandates_dismissal']);
    }

    /** @test */
    public function test_detects_misdemeanor_pretexting()
    {
        $this->testCase->update([
            'description' => 'Defendant charged with jaywalking (misdemeanor). However, police executed full search warrant on home and conducted extensive surveillance typically reserved for felonies.',
        ]);

        // Mock OpenAI response detecting pretexting
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'pretexting_detected' => true,
                                'confidence' => 80,
                                'initial_charge' => 'Jaywalking (misdemeanor)',
                                'actual_target' => 'Drug trafficking investigation (felony)',
                                'reasoning' => 'Investigative tactics (home search warrant, extensive surveillance) grossly disproportionate to minor jaywalking charge. Clear pretext.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($this->testCase->fresh());

        $this->assertNotEmpty($violations);

        $pretextViolations = array_filter($violations, fn ($v) => ($v['type'] ?? '') === 'misdemeanor_pretexting');
        $this->assertNotEmpty($pretextViolations);

        $violation = reset($pretextViolations);
        $this->assertEquals('misdemeanor_pretexting', $violation['type']);
        $this->assertEquals(75, $violation['severity']);
        $this->assertStringContainsString('pretext', strtolower($violation['description']));
    }

    /** @test */
    public function test_returns_empty_array_when_no_misconduct()
    {
        // Create a clean case with proper procedures
        $cleanCase = LegalCase::factory()->create([
            'title' => 'Clean Case',
            'description' => 'All procedures followed correctly',
            'status' => 'active',
        ]);

        // Mock OpenAI responses indicating no violations
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'fabricated' => false,
                                'confidence' => 20,
                                'hidden_evidence_detected' => false,
                                'violations_found' => false,
                                'pretexting_detected' => false,
                                'inconsistencies_found' => false,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $violations = $this->detector->detect($cleanCase);

        $this->assertIsArray($violations);
        $this->assertEmpty($violations);
    }

    /** @test */
    public function test_pattern_analyzer_finds_repeated_violations()
    {
        $instances = [
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'timestamp' => now()->subDays(3)->toIso8601String(),
            ],
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'timestamp' => now()->subDays(1)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'severity' => 90,
                'timestamp' => now()->subDays(4)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'severity' => 90,
                'timestamp' => now()->subDays(2)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('repeated_violations', $patterns);
        $this->assertNotEmpty($patterns['repeated_violations']);

        // Should detect hidden_evidence repeated 3 times
        $this->assertArrayHasKey('hidden_evidence', $patterns['repeated_violations']);
        $this->assertEquals(3, $patterns['repeated_violations']['hidden_evidence']['count']);
        $this->assertEquals('high', $patterns['repeated_violations']['hidden_evidence']['pattern_significance']);

        // Should detect rights_violation repeated 2 times
        $this->assertArrayHasKey('rights_violation', $patterns['repeated_violations']);
        $this->assertEquals(2, $patterns['repeated_violations']['rights_violation']['count']);
    }

    /** @test */
    public function test_pattern_analyzer_detects_escalating_severity()
    {
        // Create violations with increasing severity over time
        $instances = [
            [
                'type' => 'rights_violation',
                'severity' => 70,
                'timestamp' => now()->subDays(10)->toIso8601String(),
            ],
            [
                'type' => 'backdated_document',
                'severity' => 75,
                'timestamp' => now()->subDays(8)->toIso8601String(),
            ],
            [
                'type' => 'hidden_evidence',
                'severity' => 90,
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'prosecutor_threats_lying',
                'severity' => 95,
                'timestamp' => now()->subDays(2)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('escalating_severity', $patterns);
        $this->assertTrue($patterns['escalating_severity']);
    }

    /** @test */
    public function test_pattern_analyzer_identifies_rights_violation_pattern()
    {
        $instances = [
            [
                'type' => 'rights_violation',
                'description' => 'No lawyer present during interrogation',
                'severity' => 90,
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'description' => 'Defendant not informed of rights',
                'severity' => 90,
                'timestamp' => now()->subDays(3)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'description' => 'Coercion detected - threats used',
                'severity' => 90,
                'timestamp' => now()->subDays(1)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('rights_violations_pattern', $patterns);
        $this->assertNotEmpty($patterns['rights_violations_pattern']);

        $rightsPattern = $patterns['rights_violations_pattern'];
        $this->assertEquals(3, $rightsPattern['total_rights_violations']);
        $this->assertEquals('critical', $rightsPattern['severity']);
        $this->assertArrayHasKey('violated_rights', $rightsPattern);
        $this->assertStringContainsString('Judicial Council', $rightsPattern['recommendation']);
    }

    /** @test */
    public function test_pattern_analyzer_identifies_evidence_suppression_pattern()
    {
        $instances = [
            [
                'type' => 'hidden_evidence',
                'description' => 'Brady violation - exculpatory evidence withheld',
                'severity' => 95,
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'backdated_document',
                'description' => 'Document backdated',
                'severity' => 85,
                'timestamp' => now()->subDays(3)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('evidence_suppression_pattern', $patterns);
        $this->assertNotEmpty($patterns['evidence_suppression_pattern']);

        $suppressionPattern = $patterns['evidence_suppression_pattern'];
        $this->assertEquals(2, $suppressionPattern['total_evidence_violations']);
        $this->assertEquals('critical', $suppressionPattern['severity']);
        $this->assertStringContainsString('dismissal', $suppressionPattern['recommendation']);
    }

    /** @test */
    public function test_pattern_analyzer_detects_systemic_issues()
    {
        $instances = [
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'prosecutor' => 'Ivana Horvat',
                'police_unit' => 'Zagreb Unit 1',
                'timestamp' => now()->subDays(10)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'severity' => 90,
                'prosecutor' => 'Ivana Horvat',
                'police_unit' => 'Zagreb Unit 1',
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'fabricated_probable_cause',
                'severity' => 90,
                'prosecutor' => 'Ivana Horvat',
                'police_unit' => 'Zagreb Unit 1',
                'timestamp' => now()->subDays(2)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('systemic_issues', $patterns);
        $this->assertNotEmpty($patterns['systemic_issues']);

        // Should detect prosecutor pattern
        $prosecutorIssue = collect($patterns['systemic_issues'])->firstWhere('type', 'prosecutor_pattern');
        $this->assertNotNull($prosecutorIssue);
        $this->assertEquals('Ivana Horvat', $prosecutorIssue['actor']);
        $this->assertEquals(3, $prosecutorIssue['violation_count']);
        $this->assertEquals('critical', $prosecutorIssue['severity']);

        // Should detect police unit pattern
        $policeIssue = collect($patterns['systemic_issues'])->firstWhere('type', 'police_unit_pattern');
        $this->assertNotNull($policeIssue);
        $this->assertEquals('Zagreb Unit 1', $policeIssue['actor']);

        // Should detect coordinated misconduct
        $coordinatedIssue = collect($patterns['systemic_issues'])->firstWhere('type', 'coordinated_misconduct');
        $this->assertNotNull($coordinatedIssue);
        $this->assertEquals('critical', $coordinatedIssue['severity']);
    }

    /** @test */
    public function test_pattern_severity_calculated_correctly()
    {
        $instances = [
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'prosecutor' => 'Ivana Horvat',
                'timestamp' => now()->subDays(5)->toIso8601String(),
            ],
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'prosecutor' => 'Ivana Horvat',
                'timestamp' => now()->subDays(3)->toIso8601String(),
            ],
            [
                'type' => 'rights_violation',
                'description' => 'No lawyer present',
                'severity' => 90,
                'prosecutor' => 'Ivana Horvat',
                'timestamp' => now()->subDays(1)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('pattern_severity', $patterns);
        $this->assertGreaterThan(0, $patterns['pattern_severity']);
        $this->assertLessThanOrEqual(100, $patterns['pattern_severity']);

        // With repeated violations, systemic issues, should have high severity
        $this->assertGreaterThan(70, $patterns['pattern_severity']);
    }

    /** @test */
    public function test_pattern_summary_generated_correctly()
    {
        $instances = [
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'timestamp' => now()->subDays(3)->toIso8601String(),
            ],
            [
                'type' => 'hidden_evidence',
                'severity' => 95,
                'timestamp' => now()->subDays(1)->toIso8601String(),
            ],
        ];

        $patterns = $this->analyzer->analyzePatterns($instances, $this->testCase);

        $this->assertArrayHasKey('summary', $patterns);
        $this->assertIsString($patterns['summary']);
        $this->assertNotEmpty($patterns['summary']);
        $this->assertStringContainsString('hidden_evidence', $patterns['summary']);
    }

    /** @test */
    public function test_get_recommendations_from_patterns()
    {
        $patterns = [
            'repeated_violations' => ['hidden_evidence' => ['count' => 2]],
            'escalating_severity' => true,
            'rights_violations_pattern' => [
                'total_rights_violations' => 3,
                'severity' => 'critical',
                'recommendation' => 'File complaint with Judicial Council',
            ],
            'evidence_suppression_pattern' => [
                'total_evidence_violations' => 2,
                'recommendation' => 'File dismissal motion',
            ],
            'systemic_issues' => [
                [
                    'type' => 'prosecutor_pattern',
                    'legal_action' => 'disciplinary_complaint',
                    'recommendation' => 'File complaint with Chief State Attorney',
                ],
            ],
        ];

        $recommendations = $this->analyzer->getRecommendations($patterns);

        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);

        // Should recommend dismissal motion
        $dismissalRec = collect($recommendations)->firstWhere('action', 'file_dismissal_motion');
        $this->assertNotNull($dismissalRec);
        $this->assertEquals('urgent', $dismissalRec['priority']);

        // Should recommend judicial complaint
        $judicialRec = collect($recommendations)->firstWhere('action', 'file_judicial_complaint');
        $this->assertNotNull($judicialRec);
    }
}
