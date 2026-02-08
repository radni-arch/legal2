<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\TopicAnalyzer;
use App\Models\LegalCase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class TopicAnalyzerTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test 1: Component renders correctly
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        Livewire::test(TopicAnalyzer::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.topic-analyzer')
            ->assertSet('selectedTopic', 'drug_charge_severity')
            ->assertSet('drugType', 'cannabis')
            ->assertSet('amount', 30)
            ->assertSet('chargedAs', 'dealing')
            ->assertSet('activeTab', 'analyze')
            ->assertSet('loading', false);
    }

    /**
     * Test 2: Topic selection works
     *
     * @test
     */
    public function test_topic_selection_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('selectedTopic', 'drug_charge_severity')
            ->assertSet('selectedTopic', 'drug_charge_severity')
            ->set('selectedTopic', 'home_search_abuse')
            ->assertSet('selectedTopic', 'home_search_abuse');
    }

    /**
     * Test 3: Drug type selection works
     *
     * @test
     */
    public function test_drug_type_selection_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('drugType', 'cannabis')
            ->assertSet('drugType', 'cannabis')
            ->set('drugType', 'cocaine')
            ->assertSet('drugType', 'cocaine');
    }

    /**
     * Test 4: Amount input validation works
     *
     * @test
     */
    public function test_amount_validation_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('amount', -10)
            ->call('analyzeCase')
            ->assertHasErrors(['amount']);
    }

    /**
     * Test 5: Charge type selection works
     *
     * @test
     */
    public function test_charge_type_selection_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('chargedAs', 'dealing')
            ->assertSet('chargedAs', 'dealing')
            ->set('chargedAs', 'personal_use')
            ->assertSet('chargedAs', 'personal_use');
    }

    /**
     * Test 6: Tab navigation works
     *
     * @test
     */
    public function test_tab_navigation_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->assertSet('activeTab', 'analyze')
            ->set('activeTab', 'statistics')
            ->assertSet('activeTab', 'statistics')
            ->set('activeTab', 'compare')
            ->assertSet('activeTab', 'compare');
    }

    /**
     * Test 7: Statistics year validation works
     *
     * @test
     */
    public function test_statistics_year_validation_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('statsYear', 2015)
            ->call('getStatistics')
            ->assertHasErrors(['statsYear']);
    }

    /**
     * Test 8: Region selection works
     *
     * @test
     */
    public function test_region_selection_works()
    {
        Livewire::test(TopicAnalyzer::class)
            ->set('region1', 'Osijek')
            ->assertSet('region1', 'Osijek')
            ->set('region2', 'Zagreb')
            ->assertSet('region2', 'Zagreb');
    }

    /**
     * Test 9: Evidence options property is available
     *
     * @test
     */
    public function test_evidence_options_available()
    {
        $component = Livewire::test(TopicAnalyzer::class);

        $evidenceOptions = $component->get('evidenceOptions');

        $this->assertIsArray($evidenceOptions);
        $this->assertArrayHasKey('scales', $evidenceOptions);
        $this->assertArrayHasKey('baggies', $evidenceOptions);
        $this->assertArrayHasKey('large_cash', $evidenceOptions);
        $this->assertArrayHasKey('phone_records', $evidenceOptions);
    }

    /**
     * Test 10: Topics property contains expected topics
     *
     * @test
     */
    public function test_topics_property_contains_expected_topics()
    {
        $component = Livewire::test(TopicAnalyzer::class);

        $topics = $component->get('topics');

        $this->assertIsArray($topics);
        $this->assertArrayHasKey('drug_charge_severity', $topics);
        $this->assertArrayHasKey('home_search_abuse', $topics);
    }

    /**
     * Test 11: Drug types property contains expected types
     *
     * @test
     */
    public function test_drug_types_property_contains_expected_types()
    {
        $component = Livewire::test(TopicAnalyzer::class);

        $drugTypes = $component->get('drugTypes');

        $this->assertIsArray($drugTypes);
        $this->assertArrayHasKey('cannabis', $drugTypes);
        $this->assertArrayHasKey('cocaine', $drugTypes);
        $this->assertArrayHasKey('heroin', $drugTypes);
        $this->assertArrayHasKey('ecstasy', $drugTypes);
        $this->assertArrayHasKey('amphetamine', $drugTypes);
    }

    /**
     * Test 12: Regions property contains expected regions
     *
     * @test
     */
    public function test_regions_property_contains_expected_regions()
    {
        $component = Livewire::test(TopicAnalyzer::class);

        $regions = $component->get('regions');

        $this->assertIsArray($regions);
        $this->assertContains('Osijek', $regions);
        $this->assertContains('Zagreb', $regions);
        $this->assertContains('Split', $regions);
    }

    /**
     * Test 13: Mount sets default case if available
     *
     * @test
     */
    public function test_mount_sets_default_case_if_available()
    {
        $case = LegalCase::factory()->create();

        $component = Livewire::test(TopicAnalyzer::class);

        $selectedCaseId = $component->get('selectedCaseId');

        $this->assertEquals($case->id, $selectedCaseId);
    }

    /**
     * Test 14: Results are initially null
     *
     * @test
     */
    public function test_results_initially_null()
    {
        Livewire::test(TopicAnalyzer::class)
            ->assertSet('analysisResult', null)
            ->assertSet('statisticsResult', null)
            ->assertSet('comparisonResult', null);
    }

    /**
     * Test 15: Error message is initially null
     *
     * @test
     */
    public function test_error_message_initially_null()
    {
        Livewire::test(TopicAnalyzer::class)
            ->assertSet('errorMessage', null);
    }

    /**
     * Test 16: Drug charge detection analyzes overcharging
     *
     * @test
     */
    public function test_drug_charge_detection_analyzes_overcharging()
    {
        $case = LegalCase::factory()->create();

        // Mock the DrugChargeAbuseDetector
        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 85,
            'drug_type' => 'cannabis',
            'amount' => 35.0,
            'charged_as' => 'dealing',
            'threshold' => 30.0,
            'overcharge_reason' => 'Amount (35g) barely exceeds threshold (30g) for cannabis',
            'recommendation' => 'Challenge dealing charge - amount suggests personal use',
            'legal_basis' => ['KZ Čl. 173', 'KZ Čl. 190'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->set('drugType', 'cannabis')
            ->set('amount', 35)
            ->set('chargedAs', 'dealing')
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_overcharged']);
        $this->assertEquals(85, $result['severity_score']);
        $this->assertEquals('cannabis', $result['drug_type']);
        $this->assertStringContainsString('threshold', $result['overcharge_reason']);
    }

    /**
     * Test 17: Drug charge detection identifies valid charges
     *
     * @test
     */
    public function test_drug_charge_detection_identifies_valid_charges()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => false,
            'severity_score' => 15,
            'drug_type' => 'cannabis',
            'amount' => 200.0,
            'charged_as' => 'dealing',
            'threshold' => 30.0,
            'overcharge_reason' => null,
            'recommendation' => 'Charge appears proportionate to evidence',
            'legal_basis' => ['KZ Čl. 190'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->set('drugType', 'cannabis')
            ->set('amount', 200)
            ->set('chargedAs', 'dealing')
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertFalse($result['is_overcharged']);
        $this->assertEquals(15, $result['severity_score']);
        $this->assertEquals(200.0, $result['amount']);
    }

    /**
     * Test 18: Severity scoring rates high severity correctly
     *
     * @test
     */
    public function test_severity_scoring_rates_high_severity()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 95,
            'drug_type' => 'cocaine',
            'amount' => 1.5,
            'charged_as' => 'dealing',
            'threshold' => 1.0,
            'overcharge_reason' => 'Minimal amount over threshold with no dealing evidence',
            'evidence_of_dealing' => [],
            'recommendation' => 'URGENT: File motion to reduce charge immediately',
            'legal_basis' => ['KZ Čl. 173', 'KZ Čl. 190', 'ZKP Čl. 9'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->set('drugType', 'cocaine')
            ->set('amount', 1.5)
            ->set('chargedAs', 'dealing')
            ->set('evidenceOfDealing', [])
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_overcharged']);
        $this->assertGreaterThanOrEqual(90, $result['severity_score']);
        $this->assertStringContainsString('URGENT', $result['recommendation']);
        $this->assertEmpty($result['evidence_of_dealing']);
    }

    /**
     * Test 19: Severity scoring rates medium severity correctly
     *
     * @test
     */
    public function test_severity_scoring_rates_medium_severity()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 65,
            'drug_type' => 'cannabis',
            'amount' => 50.0,
            'charged_as' => 'dealing',
            'threshold' => 30.0,
            'overcharge_reason' => 'Amount moderately exceeds threshold',
            'evidence_of_dealing' => ['baggies'],
            'recommendation' => 'Challenge dealing charge - limited evidence',
            'legal_basis' => ['KZ Čl. 173', 'KZ Čl. 190'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->set('drugType', 'cannabis')
            ->set('amount', 50)
            ->set('chargedAs', 'dealing')
            ->set('evidenceOfDealing', ['baggies'])
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_overcharged']);
        $this->assertGreaterThanOrEqual(60, $result['severity_score']);
        $this->assertLessThan(75, $result['severity_score']);
        $this->assertContains('baggies', $result['evidence_of_dealing']);
    }

    /**
     * Test 20: Severity scoring rates low severity correctly
     *
     * @test
     */
    public function test_severity_scoring_rates_low_severity()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => false,
            'severity_score' => 25,
            'drug_type' => 'cannabis',
            'amount' => 150.0,
            'charged_as' => 'dealing',
            'threshold' => 30.0,
            'overcharge_reason' => null,
            'evidence_of_dealing' => ['scales', 'baggies', 'large_cash', 'phone_records'],
            'recommendation' => 'Charge appears proportionate - substantial evidence present',
            'legal_basis' => ['KZ Čl. 190'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->set('drugType', 'cannabis')
            ->set('amount', 150)
            ->set('chargedAs', 'dealing')
            ->set('evidenceOfDealing', ['scales', 'baggies', 'large_cash', 'phone_records'])
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertFalse($result['is_overcharged']);
        $this->assertLessThan(30, $result['severity_score']);
        $this->assertCount(4, $result['evidence_of_dealing']);
        $this->assertStringContainsString('proportionate', $result['recommendation']);
    }

    /**
     * Test 21: Recommendation generation for high severity cases
     *
     * @test
     */
    public function test_recommendation_generation_for_high_severity_cases()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 92,
            'recommendation' => 'URGENT: File motion to reduce charge immediately. Cite KZ Čl. 173 for personal use.',
            'actions' => [
                'File motion to reduce charge from dealing to personal use',
                'Request dismissal based on disproportionate charging',
                'Cite lack of dealing evidence in motion',
                'Reference similar cases with proportionate charges',
            ],
            'legal_basis' => ['KZ Čl. 173', 'KZ Čl. 190', 'ZKP Čl. 9'],
            'case_law_references' => ['Rev-1234/2024', 'Kž-5678/2023'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('legal_basis', $result);
        $this->assertArrayHasKey('case_law_references', $result);

        $this->assertStringContainsString('URGENT', $result['recommendation']);
        $this->assertStringContainsString('File motion', $result['recommendation']);
        $this->assertGreaterThanOrEqual(3, count($result['actions']));
        $this->assertContains('KZ Čl. 173', $result['legal_basis']);
    }

    /**
     * Test 22: Recommendation generation for medium severity cases
     *
     * @test
     */
    public function test_recommendation_generation_for_medium_severity_cases()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 60,
            'recommendation' => 'Challenge dealing charge - amount suggests borderline case.',
            'actions' => [
                'Request detailed evidence inventory',
                'Challenge interpretation of dealing evidence',
                'Prepare alternative personal use argument',
            ],
            'legal_basis' => ['KZ Čl. 173', 'KZ Čl. 190'],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertArrayHasKey('actions', $result);

        $this->assertStringContainsString('Challenge', $result['recommendation']);
        $this->assertGreaterThanOrEqual(2, count($result['actions']));
    }

    /**
     * Test 23: Recommendation generation includes legal citations
     *
     * @test
     */
    public function test_recommendation_generation_includes_legal_citations()
    {
        $case = LegalCase::factory()->create();

        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('analyzeCase')->willReturn([
            'is_overcharged' => true,
            'severity_score' => 88,
            'recommendation' => 'File motion citing KZ Čl. 173 and ZKP Čl. 9',
            'legal_basis' => [
                'KZ Čl. 173 (osobna uporaba)',
                'KZ Čl. 190 (neovlaštena trgovina)',
                'ZKP Čl. 9 (načelo razmjernosti)',
            ],
            'croatian_case_law' => [
                'Vrhovni sud Rev-1234/2024 - similar case reduced to personal use',
                'Županijski sud Osijek Kž-5678/2023 - proportionality principle',
            ],
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('selectedCaseId', $case->id)
            ->call('analyzeCase');

        $result = $component->get('analysisResult');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('legal_basis', $result);
        $this->assertArrayHasKey('croatian_case_law', $result);

        $this->assertGreaterThanOrEqual(2, count($result['legal_basis']));
        $this->assertContains('KZ Čl. 173 (osobna uporaba)', $result['legal_basis']);
        $this->assertContains('ZKP Čl. 9 (načelo razmjernosti)', $result['legal_basis']);

        $this->assertGreaterThanOrEqual(1, count($result['croatian_case_law']));
        $this->assertStringContainsString('Vrhovni sud', $result['croatian_case_law'][0]);
    }

    /**
     * Test 24: Statistics retrieval works correctly
     *
     * @test
     */
    public function test_statistics_retrieval_works_correctly()
    {
        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('getStatistics')->willReturn([
            'year' => 2025,
            'region' => 'Osijek',
            'total_cases' => 145,
            'overcharged_cases' => 87,
            'overcharge_rate' => 60.0,
            'avg_severity_score' => 72.5,
            'most_common_drug' => 'cannabis',
            'avg_amount_charged' => 45.8,
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('statsYear', 2025)
            ->set('statsRegion', 'Osijek')
            ->call('getStatistics');

        $result = $component->get('statisticsResult');

        $this->assertNotNull($result);
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals('Osijek', $result['region']);
        $this->assertEquals(145, $result['total_cases']);
        $this->assertEquals(87, $result['overcharged_cases']);
        $this->assertEquals(60.0, $result['overcharge_rate']);
        $this->assertGreaterThan(70, $result['avg_severity_score']);
    }

    /**
     * Test 25: Regional comparison works correctly
     *
     * @test
     */
    public function test_regional_comparison_works_correctly()
    {
        $mockDetector = $this->createMock(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class);
        $mockDetector->method('compareRegions')->willReturn([
            'region1' => 'Osijek',
            'region2' => 'Zagreb',
            'year' => 2025,
            'region1_stats' => [
                'total_cases' => 145,
                'overcharge_rate' => 60.0,
                'avg_severity_score' => 72.5,
            ],
            'region2_stats' => [
                'total_cases' => 320,
                'overcharge_rate' => 42.0,
                'avg_severity_score' => 58.3,
            ],
            'difference' => [
                'overcharge_rate_diff' => 18.0,
                'severity_score_diff' => 14.2,
                'statistical_significance' => true,
            ],
            'conclusion' => 'Osijek shows significantly higher overcharging rate than Zagreb',
        ]);

        $this->app->instance(\App\Modules\Topics\Analyzers\DrugChargeAbuseDetector::class, $mockDetector);

        $component = Livewire::test(TopicAnalyzer::class)
            ->set('region1', 'Osijek')
            ->set('region2', 'Zagreb')
            ->set('comparisonYear', 2025)
            ->call('compareRegions');

        $result = $component->get('comparisonResult');

        $this->assertNotNull($result);
        $this->assertEquals('Osijek', $result['region1']);
        $this->assertEquals('Zagreb', $result['region2']);
        $this->assertArrayHasKey('region1_stats', $result);
        $this->assertArrayHasKey('region2_stats', $result);
        $this->assertArrayHasKey('difference', $result);
        $this->assertTrue($result['difference']['statistical_significance']);
        $this->assertGreaterThan(15, $result['difference']['overcharge_rate_diff']);
    }
}
