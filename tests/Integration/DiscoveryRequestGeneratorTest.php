<?php

namespace Tests\Integration;

use App\Models\DiscoveryPackage;
use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\Discovery\DiscoveryRequestGenerator;

/**
 * Integration tests for Discovery Request Generator
 *
 * Tests the complete discovery request generation workflow including
 * interrogatories, document requests, admissions, and deposition notices.
 *
 * NOTE: External dependencies (OpenAI, DecisionSearch, S3) are mocked via IntegrationTestCase
 */
class DiscoveryRequestGeneratorTest extends IntegrationTestCase
{
    protected User $user;

    protected DiscoveryRequestGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->generator = app(DiscoveryRequestGenerator::class);
    }

    /** @test */
    public function it_generates_complete_discovery_package()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'legal_area' => 'contract',
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Plaintiff Corp', 'role' => 'plaintiff', 'type' => 'corporation'],
                    ['name' => 'Defendant LLC', 'role' => 'defendant', 'type' => 'corporation'],
                ],
                'legal_issues' => [
                    [
                        'issue' => 'Breach of contract',
                        'area_of_law' => 'contract',
                        'elements' => ['Valid contract', 'Breach', 'Damages'],
                    ],
                ],
                'disputed_facts' => [
                    'Whether notice was properly given',
                    'Amount of actual damages incurred',
                    'Whether force majeure clause applies',
                ],
                'undisputed_facts' => [
                    'Contract was signed on January 15, 2024',
                    'Parties had prior business relationship',
                ],
                'events' => [
                    ['description' => 'Contract signed', 'date' => '2024-01-15'],
                    ['description' => 'Breach alleged', 'date' => '2024-03-20'],
                ],
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Signed contract',
                        'availability' => 'available',
                    ],
                    [
                        'type' => 'documentary',
                        'description' => 'Email communications',
                        'availability' => 'needs_discovery',
                    ],
                    [
                        'type' => 'testimonial',
                        'description' => 'Witness accounts',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'summary' => 'Contract breach dispute',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        // Verify package structure
        $this->assertInstanceOf(DiscoveryPackage::class, $package);
        $this->assertEquals($this->user->id, $package->user_id);
        $this->assertEquals($factPattern->id, $package->fact_pattern_id);

        // Verify all discovery components present
        $requests = $package->requests;
        $this->assertArrayHasKey('interrogatories', $requests);
        $this->assertArrayHasKey('document_requests', $requests);
        $this->assertArrayHasKey('requests_for_admission', $requests);
        $this->assertArrayHasKey('deposition_notices', $requests);

        // Verify interrogatories
        $interrogatories = $requests['interrogatories'];
        $this->assertArrayHasKey('total', $interrogatories);
        $this->assertArrayHasKey('items', $interrogatories);
        $this->assertGreaterThan(0, $interrogatories['total']);

        // Verify document requests
        $documentRequests = $requests['document_requests'];
        $this->assertArrayHasKey('total', $documentRequests);
        $this->assertArrayHasKey('items', $documentRequests);
        $this->assertGreaterThan(0, $documentRequests['total']);

        // Verify admissions
        $admissions = $requests['requests_for_admission'];
        $this->assertArrayHasKey('total', $admissions);
        $this->assertArrayHasKey('items', $admissions);
        $this->assertGreaterThan(0, $admissions['total']);

        // Verify metadata
        $this->assertNotNull($package->metadata);
        $this->assertArrayHasKey('estimated_cost', $package->metadata);
        $this->assertArrayHasKey('timeline', $package->metadata);
    }

    /** @test */
    public function it_generates_interrogatories_for_disputed_facts()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'disputed_facts' => [
                    'Whether defendant knew of the defect',
                    'When defendant became aware of the problem',
                    'Whether defendant took corrective action',
                ],
                'legal_issues' => [
                    ['issue' => 'Product liability', 'area_of_law' => 'tort'],
                ],
                'parties' => [
                    ['name' => 'Consumer', 'role' => 'plaintiff'],
                    ['name' => 'Manufacturer', 'role' => 'defendant'],
                ],
                'events' => [],
                'evidence' => [],
                'summary' => 'Product defect case',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $interrogatories = $package->requests['interrogatories']['items'];

        // Should have interrogatories addressing disputed facts
        $hasDisputedFactQuestions = collect($interrogatories)->contains(function ($interrogatory) {
            $text = strtolower($interrogatory['text']);

            return str_contains($text, 'defect') ||
                   str_contains($text, 'aware') ||
                   str_contains($text, 'knowledge');
        });

        $this->assertTrue($hasDisputedFactQuestions);

        // Each interrogatory should have proper structure
        foreach ($interrogatories as $interrogatory) {
            $this->assertArrayHasKey('number', $interrogatory);
            $this->assertArrayHasKey('text', $interrogatory);
            $this->assertArrayHasKey('category', $interrogatory);
            $this->assertNotEmpty($interrogatory['text']);
        }
    }

    /** @test */
    public function it_generates_document_requests_for_missing_evidence()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'evidence' => [
                    [
                        'type' => 'documentary',
                        'description' => 'Internal emails about incident',
                        'availability' => 'needs_discovery',
                    ],
                    [
                        'type' => 'documentary',
                        'description' => 'Financial records',
                        'availability' => 'needs_discovery',
                    ],
                    [
                        'type' => 'documentary',
                        'description' => 'Meeting minutes',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'events' => [
                    ['description' => 'Meeting held', 'date' => '2024-01-15'],
                ],
                'legal_issues' => [],
                'parties' => [],
                'summary' => 'Case needing documentary evidence',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $documentRequests = $package->requests['document_requests']['items'];

        $this->assertGreaterThan(0, count($documentRequests));

        // Should request emails
        $hasEmailRequest = collect($documentRequests)->contains(function ($request) {
            return str_contains(strtolower($request['description']), 'email');
        });
        $this->assertTrue($hasEmailRequest);

        // Should request financial records
        $hasFinancialRequest = collect($documentRequests)->contains(function ($request) {
            return str_contains(strtolower($request['description']), 'financial');
        });
        $this->assertTrue($hasFinancialRequest);

        // Each request should have proper structure
        foreach ($documentRequests as $request) {
            $this->assertArrayHasKey('number', $request);
            $this->assertArrayHasKey('description', $request);
            $this->assertArrayHasKey('category', $request);
            $this->assertArrayHasKey('relevance', $request);
        }
    }

    /** @test */
    public function it_generates_requests_for_admission_for_undisputed_facts()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'undisputed_facts' => [
                    'Contract was signed on March 1, 2024',
                    'Defendant received the goods',
                    'Payment was due on April 1, 2024',
                ],
                'disputed_facts' => [
                    'Whether goods were defective',
                ],
                'legal_issues' => [],
                'parties' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Contract dispute',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $admissions = $package->requests['requests_for_admission']['items'];

        $this->assertGreaterThan(0, count($admissions));

        // Should have admissions for undisputed facts
        $hasContractAdmission = collect($admissions)->contains(function ($admission) {
            return str_contains(strtolower($admission['statement']), 'contract') &&
                   str_contains(strtolower($admission['statement']), 'march');
        });

        // Each admission should have proper structure
        foreach ($admissions as $admission) {
            $this->assertArrayHasKey('number', $admission);
            $this->assertArrayHasKey('statement', $admission);
            $this->assertArrayHasKey('purpose', $admission);
        }
    }

    /** @test */
    public function it_generates_deposition_notices()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'John Smith', 'role' => 'defendant', 'type' => 'individual'],
                    ['name' => 'ABC Corporation', 'role' => 'defendant', 'type' => 'corporation'],
                ],
                'evidence' => [
                    [
                        'type' => 'testimonial',
                        'description' => 'CEO testimony about decision',
                        'availability' => 'needs_discovery',
                    ],
                ],
                'events' => [
                    ['description' => 'Key meeting held', 'date' => '2024-02-15'],
                ],
                'legal_issues' => [],
                'summary' => 'Case requiring depositions',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $depositions = $package->requests['deposition_notices'];

        $this->assertArrayHasKey('total', $depositions);
        $this->assertArrayHasKey('notices', $depositions);
        $this->assertGreaterThan(0, $depositions['total']);

        // Should include party depositions
        $notices = $depositions['notices'];
        foreach ($notices as $notice) {
            $this->assertArrayHasKey('deponent', $notice);
            $this->assertArrayHasKey('role', $notice);
            $this->assertArrayHasKey('topics', $notice);
            $this->assertArrayHasKey('estimated_duration', $notice);
        }
    }

    /** @test */
    public function it_provides_cost_estimate()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'disputed_facts' => ['Fact 1', 'Fact 2'],
                'evidence' => [
                    ['type' => 'documentary', 'availability' => 'needs_discovery'],
                    ['type' => 'testimonial', 'availability' => 'needs_discovery'],
                ],
                'parties' => [
                    ['name' => 'Defendant', 'role' => 'defendant'],
                ],
                'legal_issues' => [],
                'events' => [],
                'summary' => 'Discovery cost test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $costEstimate = $package->metadata['estimated_cost'];

        $this->assertArrayHasKey('total_min', $costEstimate);
        $this->assertArrayHasKey('total_max', $costEstimate);
        $this->assertArrayHasKey('breakdown', $costEstimate);

        // Cost should be positive
        $this->assertGreaterThan(0, $costEstimate['total_min']);
        $this->assertGreaterThan($costEstimate['total_min'], $costEstimate['total_max']);

        // Breakdown should include major categories
        $breakdown = $costEstimate['breakdown'];
        $this->assertArrayHasKey('interrogatories', $breakdown);
        $this->assertArrayHasKey('document_production', $breakdown);
        $this->assertArrayHasKey('depositions', $breakdown);
    }

    /** @test */
    public function it_provides_discovery_timeline()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'disputed_facts' => ['Fact 1'],
                'evidence' => [
                    ['type' => 'documentary', 'availability' => 'needs_discovery'],
                ],
                'legal_issues' => [],
                'parties' => [],
                'events' => [],
                'summary' => 'Timeline test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $timeline = $package->metadata['timeline'];

        $this->assertArrayHasKey('phase_1', $timeline);
        $this->assertArrayHasKey('phase_2', $timeline);
        $this->assertArrayHasKey('phase_3', $timeline);

        // Each phase should have details
        foreach (['phase_1', 'phase_2', 'phase_3'] as $phase) {
            $this->assertArrayHasKey('days', $timeline[$phase]);
            $this->assertArrayHasKey('description', $timeline[$phase]);
            $this->assertArrayHasKey('activities', $timeline[$phase]);
        }
    }

    /** @test */
    public function it_generates_formatted_interrogatories_document()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Plaintiff Inc', 'role' => 'plaintiff'],
                    ['name' => 'Defendant Corp', 'role' => 'defendant'],
                ],
                'disputed_facts' => ['Key disputed fact'],
                'legal_issues' => [
                    ['issue' => 'Liability', 'area_of_law' => 'tort'],
                ],
                'events' => [],
                'evidence' => [],
                'summary' => 'Format test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $formatted = $this->generator->generateFormattedDocument($package->id, 'interrogatories');

        $this->assertNotEmpty($formatted);
        $this->assertIsString($formatted);

        // Should have proper legal document formatting
        $this->assertStringContainsString('INTERROGATORIES', $formatted);
        $this->assertStringContainsString('Plaintiff', $formatted);
        $this->assertStringContainsString('Defendant', $formatted);
    }

    /** @test */
    public function it_generates_formatted_document_requests_document()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Requesting Party', 'role' => 'plaintiff'],
                    ['name' => 'Responding Party', 'role' => 'defendant'],
                ],
                'evidence' => [
                    ['type' => 'documentary', 'description' => 'Contracts', 'availability' => 'needs_discovery'],
                ],
                'legal_issues' => [],
                'events' => [],
                'summary' => 'Document request test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $formatted = $this->generator->generateFormattedDocument($package->id, 'document_requests');

        $this->assertNotEmpty($formatted);
        $this->assertIsString($formatted);

        // Should have proper formatting
        $this->assertStringContainsString('REQUESTS FOR PRODUCTION', $formatted);
        $this->assertStringContainsString('REQUEST NO.', $formatted);
    }

    /** @test */
    public function it_categorizes_interrogatories()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'Party A', 'role' => 'plaintiff'],
                    ['name' => 'Party B', 'role' => 'defendant'],
                ],
                'disputed_facts' => ['Disputed fact 1'],
                'events' => [
                    ['description' => 'Incident', 'date' => '2024-01-01'],
                ],
                'damages_or_relief_sought' => [
                    'type' => 'monetary',
                    'amount' => '100,000',
                ],
                'evidence' => [
                    ['type' => 'testimonial', 'description' => 'Witness'],
                ],
                'legal_issues' => [],
                'summary' => 'Category test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        $interrogatories = $package->requests['interrogatories']['items'];

        $categories = array_unique(array_column($interrogatories, 'category'));

        // Should have multiple categories
        $this->assertGreaterThan(1, count($categories));

        // Common categories
        $expectedCategories = ['identification', 'background', 'facts', 'damages', 'witnesses', 'documents'];
        $foundCategories = array_intersect($categories, $expectedCategories);
        $this->assertGreaterThan(0, count($foundCategories));
    }

    /** @test */
    public function it_handles_cases_with_minimal_information()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [],
                'disputed_facts' => [],
                'evidence' => [],
                'events' => [],
                'legal_issues' => [],
                'summary' => 'Minimal information case',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        // Should still generate basic discovery requests
        $this->assertInstanceOf(DiscoveryPackage::class, $package);

        // Should have standard interrogatories
        $interrogatories = $package->requests['interrogatories'];
        $this->assertGreaterThan(0, $interrogatories['total']);

        // Should have standard document requests
        $documentRequests = $package->requests['document_requests'];
        $this->assertGreaterThan(0, $documentRequests['total']);
    }

    /** @test */
    public function it_adapts_to_case_complexity()
    {
        // Simple case
        $simplePattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'P', 'role' => 'plaintiff'],
                    ['name' => 'D', 'role' => 'defendant'],
                ],
                'disputed_facts' => ['One fact'],
                'evidence' => [
                    ['type' => 'documentary', 'availability' => 'needs_discovery'],
                ],
                'legal_issues' => [],
                'events' => [],
                'summary' => 'Simple case',
            ],
        ]);

        // Complex case
        $complexPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [
                    ['name' => 'P1', 'role' => 'plaintiff'],
                    ['name' => 'D1', 'role' => 'defendant'],
                    ['name' => 'D2', 'role' => 'defendant'],
                    ['name' => 'D3', 'role' => 'defendant'],
                ],
                'disputed_facts' => ['Fact 1', 'Fact 2', 'Fact 3', 'Fact 4', 'Fact 5'],
                'evidence' => [
                    ['type' => 'documentary', 'availability' => 'needs_discovery'],
                    ['type' => 'testimonial', 'availability' => 'needs_discovery'],
                    ['type' => 'expert', 'availability' => 'needs_discovery'],
                ],
                'legal_issues' => [
                    ['issue' => 'Issue 1'],
                    ['issue' => 'Issue 2'],
                    ['issue' => 'Issue 3'],
                ],
                'events' => [
                    ['description' => 'Event 1', 'date' => '2024-01-01'],
                    ['description' => 'Event 2', 'date' => '2024-02-01'],
                    ['description' => 'Event 3', 'date' => '2024-03-01'],
                ],
                'summary' => 'Complex multi-party case',
            ],
        ]);

        $simplePackage = $this->generator->generateDiscoveryPackage($simplePattern->id);
        $complexPackage = $this->generator->generateDiscoveryPackage($complexPattern->id);

        // Complex case should have more interrogatories
        $this->assertGreaterThan(
            $simplePackage->requests['interrogatories']['total'],
            $complexPackage->requests['interrogatories']['total']
        );

        // Complex case should have higher cost estimate
        $this->assertGreaterThan(
            $simplePackage->metadata['estimated_cost']['total_max'],
            $complexPackage->metadata['estimated_cost']['total_max']
        );
    }

    /** @test */
    public function it_stores_package_metadata()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [['name' => 'Test', 'role' => 'plaintiff']],
                'disputed_facts' => ['Test fact'],
                'legal_issues' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        $package = $this->generator->generateDiscoveryPackage($factPattern->id);

        // Should be persisted to database
        $this->assertDatabaseHas('discovery_packages', [
            'id' => $package->id,
            'user_id' => $this->user->id,
            'fact_pattern_id' => $factPattern->id,
        ]);

        // Should have metadata
        $this->assertNotNull($package->metadata);
        $this->assertArrayHasKey('generated_at', $package->metadata);
        $this->assertArrayHasKey('estimated_cost', $package->metadata);
        $this->assertArrayHasKey('timeline', $package->metadata);
    }

    /** @test */
    public function it_can_retrieve_packages_for_fact_pattern()
    {
        $factPattern = LegalFactPattern::factory()->create([
            'user_id' => $this->user->id,
            'structured_facts' => [
                'parties' => [['name' => 'Test', 'role' => 'plaintiff']],
                'disputed_facts' => ['Test'],
                'legal_issues' => [],
                'events' => [],
                'evidence' => [],
                'summary' => 'Test',
            ],
        ]);

        // Generate multiple packages
        $package1 = $this->generator->generateDiscoveryPackage($factPattern->id);
        $package2 = $this->generator->generateDiscoveryPackage($factPattern->id);

        // Retrieve all packages
        $packages = DiscoveryPackage::where('fact_pattern_id', $factPattern->id)->get();

        $this->assertCount(2, $packages);
    }
}
