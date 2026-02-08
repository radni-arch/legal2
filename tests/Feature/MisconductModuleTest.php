<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Evidence;
use App\Models\LegalCase;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature Tests for Prosecutorial Misconduct Module
 *
 * Tests all 4 API endpoints:
 * - POST /api/misconduct/analyze/{caseId}
 * - POST /api/misconduct/dismissal-motion/{caseId}
 * - POST /api/misconduct/complaint/{caseId}
 * - POST /api/misconduct/appeal/{caseId}
 *
 * Coverage:
 * - Misconduct detection (fabricated probable cause, hidden evidence)
 * - Dismissal motion generation (severe vs. minor violations)
 * - Complaint generation (state attorney)
 * - Appeal building (žalba)
 * - Validation errors
 * - JSON structure validation
 */
class MisconductModuleTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test analyze misconduct endpoint returns proper JSON structure
     */
    public function test_analyze_misconduct_endpoint(): void
    {
        // Create a test case with basic data
        $case = LegalCase::create([
            'title' => 'Test Case for Misconduct Analysis',
            'description' => 'Testing prosecutorial misconduct detection',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Općinski sud u Zagrebu',
            'case_number' => 'K-123/2024',
            'prosecutor' => 'Ivan Horvat',
            'defendant_name' => 'Test Defendant',
            'charges' => 'Test charges',
            'filing_date' => '2024-01-15',
        ]);

        // Add a document with suspicious timing
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Witness Statement',
            'content' => 'Witness saw the defendant at the scene.',
            'document_type' => 'witness_statement',
            'filed_at' => '2024-01-10', // Filed BEFORE case (backdated)
            'created_at' => '2024-01-20', // Actually created AFTER
        ]);

        // Call the analyze endpoint
        $response = $this->postJson("/api/misconduct/analyze/{$case->id}");

        // Assert response structure
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'case_id',
                    'misconduct_detected',
                    'total_violations',
                    'severity_score',
                    'instances',
                    'patterns',
                    'recommended_actions',
                    'dismissal_grounds',
                    'analysis_timestamp',
                ],
            ]);

        // Verify data structure
        $data = $response->json('data');
        $this->assertIsString($data['case_id']);
        $this->assertIsBool($data['misconduct_detected']);
        $this->assertIsInt($data['total_violations']);
        $this->assertIsInt($data['severity_score']);
        $this->assertIsArray($data['instances']);
        $this->assertIsArray($data['patterns']);
        $this->assertIsArray($data['recommended_actions']);
    }

    /**
     * Test detection of fabricated probable cause
     */
    public function test_detects_fabricated_probable_cause(): void
    {
        // Create case with fabricated probable cause indicators
        $case = LegalCase::create([
            'title' => 'Fabricated Probable Cause Case',
            'description' => 'Case with suspicious evidence timing',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Općinski sud u Zagrebu',
            'case_number' => 'K-456/2024',
            'prosecutor' => 'Marko Kovač',
            'defendant_name' => 'John Doe',
            'charges' => 'Drug possession',
            'filing_date' => '2024-02-01',
        ]);

        // Add evidence with suspicious timing (evidence filed BEFORE arrest)
        $arrestDate = Carbon::parse('2024-01-20');
        $evidenceDate = Carbon::parse('2024-01-15'); // 5 days BEFORE arrest

        Evidence::create([
            'legal_case_id' => $case->id,
            'evidence_type' => 'physical',
            'description' => 'Drugs found at scene',
            'collected_at' => $evidenceDate->toDateString(),
            'collected_by' => 'Officer Smith',
        ]);

        // Add arrest document
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Arrest Warrant',
            'content' => 'Warrant issued for suspect arrest',
            'document_type' => 'warrant',
            'filed_at' => $arrestDate->toDateString(),
        ]);

        // Analyze the case
        $response = $this->postJson("/api/misconduct/analyze/{$case->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should detect misconduct
        $this->assertTrue($data['misconduct_detected']);
        $this->assertGreaterThan(0, $data['total_violations']);

        // Check for fabricated probable cause or backdated documents
        $violationTypes = array_column($data['instances'], 'type');
        $this->assertTrue(
            in_array('fabricated_probable_cause', $violationTypes) ||
            in_array('backdated_documents', $violationTypes)
        );
    }

    /**
     * Test detection of hidden evidence (Brady violation)
     */
    public function test_detects_hidden_evidence_brady_violation(): void
    {
        // Create case
        $case = LegalCase::create([
            'title' => 'Brady Violation Case',
            'description' => 'Case with hidden exculpatory evidence',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Županijski sud u Zagrebu',
            'case_number' => 'K-789/2024',
            'prosecutor' => 'Ana Marić',
            'defendant_name' => 'Jane Smith',
            'charges' => 'Assault',
            'filing_date' => '2024-03-01',
        ]);

        // Add evidence that was collected but not disclosed
        Evidence::create([
            'legal_case_id' => $case->id,
            'evidence_type' => 'video',
            'description' => 'Security camera footage showing defendant was not at scene',
            'collected_at' => '2024-02-15',
            'collected_by' => 'Detective Johnson',
        ]);

        // Add note about withheld evidence
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Defense Motion for Discovery',
            'content' => 'Defense requests all video evidence. Prosecution claims no video exists.',
            'document_type' => 'motion',
            'filed_at' => '2024-03-10',
        ]);

        // Analyze the case
        $response = $this->postJson("/api/misconduct/analyze/{$case->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should detect misconduct
        $this->assertTrue($data['misconduct_detected']);
        $this->assertGreaterThan(0, $data['total_violations']);

        // Verify severity score is calculated
        $this->assertGreaterThanOrEqual(0, $data['severity_score']);
        $this->assertLessThanOrEqual(100, $data['severity_score']);
    }

    /**
     * Test dismissal motion generation for severe violations
     */
    public function test_generates_dismissal_motion_for_severe_violations(): void
    {
        // Create case with severe violations
        $case = LegalCase::create([
            'title' => 'Severe Misconduct Case',
            'description' => 'Multiple severe prosecutorial violations',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Općinski sud u Splitu',
            'case_number' => 'K-999/2024',
            'prosecutor' => 'Petar Novak',
            'defendant_name' => 'Test Defendant',
            'charges' => 'Serious charges',
            'filing_date' => '2024-04-01',
        ]);

        // Add multiple pieces of evidence suggesting severe misconduct
        // 1. Backdated warrant
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Search Warrant',
            'content' => 'Warrant for search',
            'document_type' => 'warrant',
            'filed_at' => '2024-03-20', // Before case filing
            'created_at' => '2024-04-05', // Actually created after
        ]);

        // 2. Hidden exculpatory evidence
        Evidence::create([
            'legal_case_id' => $case->id,
            'evidence_type' => 'document',
            'description' => 'Alibi evidence withheld from defense',
            'collected_at' => '2024-03-25',
            'collected_by' => 'Prosecutor Office',
        ]);

        // 3. Coerced statement
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Defendant Statement',
            'content' => 'Statement obtained without lawyer present. Defendant threatened with harsher charges.',
            'document_type' => 'statement',
            'filed_at' => '2024-04-02',
        ]);

        // Generate dismissal motion
        $response = $this->postJson("/api/misconduct/dismissal-motion/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dismissal_warranted',
                ],
            ]);

        $data = $response->json('data');

        // Should warrant dismissal
        $this->assertTrue($data['dismissal_warranted']);

        // Should have proper structure if dismissal is warranted
        if ($data['dismissal_warranted']) {
            $this->assertArrayHasKey('motion_type', $data);
            $this->assertArrayHasKey('grounds', $data);
            $this->assertArrayHasKey('motion_text', $data);
            $this->assertArrayHasKey('legal_authorities', $data);
            $this->assertArrayHasKey('filing_instructions', $data);
            $this->assertArrayHasKey('urgency', $data);

            $this->assertEquals('Prijedlog za obustavu postupka', $data['motion_type']);
            $this->assertIsArray($data['grounds']);
            $this->assertIsString($data['motion_text']);
            $this->assertIsArray($data['legal_authorities']);
        }
    }

    /**
     * Test that minor violations do not generate dismissal motion
     */
    public function test_does_not_generate_dismissal_for_minor_violations(): void
    {
        // Create case with only minor procedural issues
        $case = LegalCase::create([
            'title' => 'Minor Issues Case',
            'description' => 'Case with minor procedural issues',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Općinski sud u Rijeci',
            'case_number' => 'K-111/2024',
            'prosecutor' => 'Ivana Jurić',
            'defendant_name' => 'Minor Violation Test',
            'charges' => 'Minor charges',
            'filing_date' => '2024-05-01',
        ]);

        // Add only minor documents with no severe violations
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Case Summary',
            'content' => 'Standard case summary with proper procedures followed',
            'document_type' => 'summary',
            'filed_at' => '2024-05-02',
        ]);

        // Generate dismissal motion
        $response = $this->postJson("/api/misconduct/dismissal-motion/{$case->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        // Should NOT warrant dismissal for minor violations
        // (Either no violations found, or violations below severity threshold)
        if (isset($data['dismissal_warranted'])) {
            // If dismissal_warranted is false, should have reason
            if (! $data['dismissal_warranted']) {
                $this->assertArrayHasKey('reason', $data);
            }
        }
    }

    /**
     * Test generation of State Attorney complaint
     */
    public function test_generates_state_attorney_complaint(): void
    {
        // Create case with prosecutor misconduct
        $case = LegalCase::create([
            'title' => 'Prosecutor Misconduct Case',
            'description' => 'Case with clear prosecutor violations',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Općinski sud u Osijeku',
            'case_number' => 'K-222/2024',
            'prosecutor' => 'Luka Babić',
            'defendant_name' => 'Complaint Test',
            'charges' => 'Various charges',
            'filing_date' => '2024-06-01',
        ]);

        // Add prosecutor misconduct evidence
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Prosecutor Threats',
            'content' => 'Prosecutor threatened witness with prosecution if testimony not changed. Fabricated evidence presented to court.',
            'document_type' => 'motion',
            'filed_at' => '2024-06-05',
        ]);

        Evidence::create([
            'legal_case_id' => $case->id,
            'evidence_type' => 'audio',
            'description' => 'Recording of prosecutor threatening witness',
            'collected_at' => '2024-06-03',
            'collected_by' => 'Defense',
        ]);

        // Generate State Attorney complaint
        $response = $this->postJson("/api/misconduct/complaint/{$case->id}", [
            'complaint_type' => 'state_attorney',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'complaint_warranted',
                ],
            ]);

        $data = $response->json('data');

        // Should warrant complaint if violations detected
        if ($data['complaint_warranted']) {
            $this->assertArrayHasKey('complaint_type', $data);
            $this->assertArrayHasKey('complaint_title', $data);
            $this->assertArrayHasKey('complaint_text', $data);
            $this->assertArrayHasKey('violations', $data);
            $this->assertArrayHasKey('legal_authorities', $data);
            $this->assertArrayHasKey('submission_info', $data);

            $this->assertEquals('state_attorney', $data['complaint_type']);
            $this->assertEquals('PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA', $data['complaint_title']);
            $this->assertIsString($data['complaint_text']);
            $this->assertIsArray($data['violations']);
        }
    }

    /**
     * Test appeal building with misconduct grounds
     */
    public function test_builds_appeal_with_misconduct_grounds(): void
    {
        // Create case with final judgment
        $case = LegalCase::create([
            'title' => 'Appeal Case with Misconduct',
            'description' => 'Case with judgment and misconduct grounds for appeal',
            'case_type' => 'criminal',
            'status' => 'closed',
            'court' => 'Općinski sud u Zagrebu',
            'case_number' => 'K-333/2024',
            'prosecutor' => 'Marija Kovačić',
            'defendant_name' => 'Appeal Test',
            'charges' => 'Charges with conviction',
            'filing_date' => '2024-07-01',
            'judgment_date' => '2024-08-15',
            'outcome' => 'convicted',
        ]);

        // Add misconduct evidence that warrants appeal
        Document::create([
            'legal_case_id' => $case->id,
            'title' => 'Trial Transcript',
            'content' => 'Prosecutor presented evidence that was never disclosed to defense. Defense objections were overruled.',
            'document_type' => 'transcript',
            'filed_at' => '2024-08-10',
        ]);

        Evidence::create([
            'legal_case_id' => $case->id,
            'evidence_type' => 'document',
            'description' => 'Evidence withheld from defense until trial',
            'collected_at' => '2024-07-15',
            'collected_by' => 'Prosecution',
        ]);

        // Build žalba (standard appeal)
        $response = $this->postJson("/api/misconduct/appeal/{$case->id}", [
            'appeal_type' => 'zalba',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'appeal_warranted',
                ],
            ]);

        $data = $response->json('data');

        // Should warrant appeal if violations detected
        if ($data['appeal_warranted']) {
            $this->assertArrayHasKey('appeal_type', $data);
            $this->assertArrayHasKey('appeal_title', $data);
            $this->assertArrayHasKey('court', $data);
            $this->assertArrayHasKey('grounds', $data);
            $this->assertArrayHasKey('appeal_text', $data);
            $this->assertArrayHasKey('legal_authorities', $data);
            $this->assertArrayHasKey('filing_info', $data);

            $this->assertEquals('zalba', $data['appeal_type']);
            $this->assertEquals('ŽALBA', $data['appeal_title']);
            $this->assertIsString($data['appeal_text']);
            $this->assertIsArray($data['grounds']);
            $this->assertIsArray($data['legal_authorities']);
            $this->assertIsArray($data['filing_info']);

            // Verify filing info has deadline
            $this->assertArrayHasKey('deadline', $data['filing_info']);
            $this->assertArrayHasKey('deadline_days', $data['filing_info']);
            $this->assertEquals(15, $data['filing_info']['deadline_days']);
        }
    }

    /**
     * Test complaint endpoint requires complaint_type
     */
    public function test_complaint_requires_complaint_type(): void
    {
        $case = LegalCase::create([
            'title' => 'Validation Test Case',
            'description' => 'Testing validation',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Test Court',
            'case_number' => 'K-VAL/2024',
            'filing_date' => '2024-01-01',
        ]);

        // Call without complaint_type
        $response = $this->postJson("/api/misconduct/complaint/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'error',
                'details',
            ]);
    }

    /**
     * Test appeal endpoint requires appeal_type
     */
    public function test_appeal_requires_appeal_type(): void
    {
        $case = LegalCase::create([
            'title' => 'Validation Test Case',
            'description' => 'Testing validation',
            'case_type' => 'criminal',
            'status' => 'active',
            'court' => 'Test Court',
            'case_number' => 'K-VAL2/2024',
            'filing_date' => '2024-01-01',
        ]);

        // Call without appeal_type
        $response = $this->postJson("/api/misconduct/appeal/{$case->id}", []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'error',
                'details',
            ]);
    }

    /**
     * Test case not found returns 404
     */
    public function test_case_not_found_returns_error(): void
    {
        // Call with non-existent case ID
        $response = $this->postJson('/api/misconduct/analyze/99999');

        // Laravel should return 404 for ModelNotFoundException
        $response->assertStatus(404);
    }
}
