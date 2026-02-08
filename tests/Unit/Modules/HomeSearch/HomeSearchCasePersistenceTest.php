<?php

namespace Tests\Unit\Modules\HomeSearch;

use App\Mcp\OdlukeTools;
use App\Modules\HomeSearch\Models\HomeSearchCase;
use App\Modules\HomeSearch\Services\OdlukeSearchAgent;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TDD Tests for HomeSearchCase Model and OdlukeSearchAgent Persistence
 *
 * Tests database persistence, deduplication, queries, and soft deletes
 * for home search cases extracted from odluke.sudovi.hr
 */
class HomeSearchCasePersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected OdlukeSearchAgent $agent;

    protected function setUp(): void
    {
        // Set AWS environment variables for TextractService
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        parent::setUp();

        $mockOpenAI = $this->createMock(OpenAIService::class);
        $mockOdlukeTools = $this->createMock(OdlukeTools::class);
        $this->agent = new OdlukeSearchAgent($mockOpenAI, $mockOdlukeTools);
    }

    /** @test */
    public function it_persists_case_to_database()
    {
        $caseData = [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Sudac Ivan Horvat',
            'decision_date' => '2025-03-15',
            'offense_type' => 'prekršaj',
            'offense_description' => 'Prometni prekršaj',
            'offense_severity' => 'misdemeanor',
            'search_type' => 'pretres stana',
            'evidence_found' => true,
            'evidence_suppressed' => true,
            'legal_violations' => ['ZKP Čl. 179 - Nerazmjeran pretres'],
            'zkp_articles_cited' => ['215', '179', '10'],
            'proportionality_mentioned' => true,
            'constitutional_rights_mentioned' => true,
            'source_url' => 'https://odluke.sudovi.hr/case/123',
            'extraction_confidence' => 0.95,
        ];

        $result = $this->agent->persistCase($caseData);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('home_search_cases', [
            'case_number' => 'K-123/2025',
            'court' => 'Općinski sud u Osijeku',
            'offense_type' => 'prekršaj',
        ]);
    }

    /** @test */
    public function it_detects_duplicate_cases_by_case_number()
    {
        $caseData = [
            'case_number' => 'K-456/2025',
            'court' => 'Općinski sud u Osijeku',
            'decision_date' => '2025-03-20',
            'offense_type' => 'kazneno_djelo',
            'extraction_confidence' => 0.90,
        ];

        // First insertion should succeed
        $result1 = $this->agent->persistCase($caseData);
        $this->assertTrue($result1['success']);
        $this->assertEquals('created', $result1['action']);

        // Second insertion should detect duplicate
        $result2 = $this->agent->persistCase($caseData);
        $this->assertTrue($result2['success']);
        $this->assertEquals('duplicate_skipped', $result2['action']);

        // Should only have one record
        $count = DB::table('home_search_cases')->where('case_number', 'K-456/2025')->count();
        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_updates_existing_case_if_new_data_has_higher_confidence()
    {
        // Insert initial case with lower confidence
        $initialData = [
            'case_number' => 'K-789/2025',
            'court' => 'Općinski sud u Osijeku',
            'decision_date' => '2025-03-25',
            'offense_type' => 'prekršaj',
            'extraction_confidence' => 0.70,
        ];
        $this->agent->persistCase($initialData);

        // Update with higher confidence
        $updatedData = [
            'case_number' => 'K-789/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Sudac Ana Kovač',
            'decision_date' => '2025-03-25',
            'offense_type' => 'prekršaj',
            'extraction_confidence' => 0.95,
        ];
        $result = $this->agent->persistCase($updatedData);

        $this->assertTrue($result['success']);
        $this->assertEquals('updated', $result['action']);

        // Verify updated data
        $case = HomeSearchCase::where('case_number', 'K-789/2025')->first();
        $this->assertEquals('Sudac Ana Kovač', $case->judge);
        $this->assertEquals(0.95, $case->extraction_confidence);
    }

    /** @test */
    public function it_does_not_update_case_if_new_data_has_lower_confidence()
    {
        // Insert initial case with higher confidence
        $initialData = [
            'case_number' => 'K-999/2025',
            'court' => 'Općinski sud u Osijeku',
            'judge' => 'Sudac Marko Novak',
            'extraction_confidence' => 0.95,
        ];
        $this->agent->persistCase($initialData);

        // Try to update with lower confidence
        $lowerConfidenceData = [
            'case_number' => 'K-999/2025',
            'court' => 'Different Court',
            'extraction_confidence' => 0.60,
        ];
        $result = $this->agent->persistCase($lowerConfidenceData);

        $this->assertTrue($result['success']);
        $this->assertEquals('duplicate_skipped', $result['action']);

        // Verify data was NOT updated
        $case = HomeSearchCase::where('case_number', 'K-999/2025')->first();
        $this->assertEquals('Sudac Marko Novak', $case->judge);
        $this->assertEquals('Općinski sud u Osijeku', $case->court);
    }

    /** @test */
    public function it_can_query_cases_by_court()
    {
        // Insert cases for different courts
        $this->agent->persistCase([
            'case_number' => 'K-111/2025',
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-222/2025',
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-333/2025',
            'court' => 'Županijski sud u Zagrebu',
            'extraction_confidence' => 0.90,
        ]);

        $osijekCases = HomeSearchCase::where('court', 'Općinski sud u Osijeku')->get();
        $zagrebCases = HomeSearchCase::where('court', 'Županijski sud u Zagrebu')->get();

        $this->assertCount(2, $osijekCases);
        $this->assertCount(1, $zagrebCases);
    }

    /** @test */
    public function it_can_query_cases_by_date_range()
    {
        // Insert cases with different dates
        $this->agent->persistCase([
            'case_number' => 'K-201/2025',
            'decision_date' => '2025-01-15',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-202/2025',
            'decision_date' => '2025-03-20',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-203/2025',
            'decision_date' => '2025-06-10',
            'extraction_confidence' => 0.90,
        ]);

        $q1Cases = HomeSearchCase::whereBetween('decision_date', ['2025-01-01', '2025-03-31'])->get();
        $q2Cases = HomeSearchCase::whereBetween('decision_date', ['2025-04-01', '2025-06-30'])->get();

        $this->assertCount(2, $q1Cases);
        $this->assertCount(1, $q2Cases);
    }

    /** @test */
    public function it_can_query_cases_by_offense_type()
    {
        // Insert cases with different offense types
        $this->agent->persistCase([
            'case_number' => 'K-301/2025',
            'offense_type' => 'prekršaj',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-302/2025',
            'offense_type' => 'prekršaj',
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-303/2025',
            'offense_type' => 'kazneno_djelo',
            'extraction_confidence' => 0.90,
        ]);

        $misdemeanorCases = HomeSearchCase::where('offense_type', 'prekršaj')->get();
        $criminalCases = HomeSearchCase::where('offense_type', 'kazneno_djelo')->get();

        $this->assertCount(2, $misdemeanorCases);
        $this->assertCount(1, $criminalCases);
    }

    /** @test */
    public function it_supports_soft_deletes()
    {
        $caseData = [
            'case_number' => 'K-999/2025',
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.50, // Low confidence
        ];
        $this->agent->persistCase($caseData);

        $case = HomeSearchCase::where('case_number', 'K-999/2025')->first();
        $this->assertNotNull($case);

        // Soft delete
        $case->delete();

        // Should not appear in normal queries
        $this->assertNull(HomeSearchCase::where('case_number', 'K-999/2025')->first());

        // Should appear in withTrashed queries
        $trashedCase = HomeSearchCase::withTrashed()->where('case_number', 'K-999/2025')->first();
        $this->assertNotNull($trashedCase);
        $this->assertNotNull($trashedCase->deleted_at);
    }

    /** @test */
    public function it_can_restore_soft_deleted_cases()
    {
        $caseData = [
            'case_number' => 'K-888/2025',
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.85,
        ];
        $this->agent->persistCase($caseData);

        $case = HomeSearchCase::where('case_number', 'K-888/2025')->first();
        $case->delete();

        // Verify soft deleted
        $this->assertNull(HomeSearchCase::where('case_number', 'K-888/2025')->first());

        // Restore
        $case->restore();

        // Verify restored
        $restoredCase = HomeSearchCase::where('case_number', 'K-888/2025')->first();
        $this->assertNotNull($restoredCase);
        $this->assertNull($restoredCase->deleted_at);
    }

    /** @test */
    public function it_stores_json_fields_correctly()
    {
        $caseData = [
            'case_number' => 'K-777/2025',
            'legal_violations' => [
                'ZKP Čl. 179 - Nerazmjeran pretres',
                'ZKP Čl. 215 - Pretraga bez naloga',
            ],
            'zkp_articles_cited' => ['179', '215', '10', '8'],
            'extraction_confidence' => 0.92,
        ];
        $this->agent->persistCase($caseData);

        $case = HomeSearchCase::where('case_number', 'K-777/2025')->first();

        $this->assertIsArray($case->legal_violations);
        $this->assertCount(2, $case->legal_violations);
        $this->assertContains('ZKP Čl. 179 - Nerazmjeran pretres', $case->legal_violations);

        $this->assertIsArray($case->zkp_articles_cited);
        $this->assertCount(4, $case->zkp_articles_cited);
        $this->assertContains('179', $case->zkp_articles_cited);
    }

    /** @test */
    public function it_has_index_on_case_number_for_fast_lookups()
    {
        // Check that unique index exists
        $indexes = DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'home_search_cases'
            AND indexname = 'home_search_cases_case_number_unique'
        ");

        $this->assertCount(1, $indexes);
    }

    /** @test */
    public function it_records_extraction_timestamp()
    {
        $caseData = [
            'case_number' => 'K-555/2025',
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.88,
        ];
        $this->agent->persistCase($caseData);

        $case = HomeSearchCase::where('case_number', 'K-555/2025')->first();
        $this->assertNotNull($case->extracted_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $case->extracted_at);
    }

    /** @test */
    public function it_handles_missing_optional_fields_gracefully()
    {
        // Minimal required data
        $minimalData = [
            'case_number' => 'K-444/2025',
            'extraction_confidence' => 0.75,
        ];
        $result = $this->agent->persistCase($minimalData);

        $this->assertTrue($result['success']);

        $case = HomeSearchCase::where('case_number', 'K-444/2025')->first();
        $this->assertNotNull($case);
        $this->assertNull($case->court);
        $this->assertNull($case->judge);
        $this->assertNull($case->offense_type);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        // Missing case_number
        $invalidData = [
            'court' => 'Općinski sud u Osijeku',
            'extraction_confidence' => 0.90,
        ];

        $result = $this->agent->persistCase($invalidData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('case_number', $result['error']);
    }

    /** @test */
    public function it_can_query_cases_with_evidence_suppression()
    {
        // Insert cases with different suppression outcomes
        $this->agent->persistCase([
            'case_number' => 'K-601/2025',
            'evidence_suppressed' => true,
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-602/2025',
            'evidence_suppressed' => false,
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-603/2025',
            'evidence_suppressed' => true,
            'extraction_confidence' => 0.90,
        ]);

        $suppressedCases = HomeSearchCase::where('evidence_suppressed', true)->get();
        $notSuppressedCases = HomeSearchCase::where('evidence_suppressed', false)->get();

        $this->assertCount(2, $suppressedCases);
        $this->assertCount(1, $notSuppressedCases);
    }

    /** @test */
    public function it_can_query_cases_mentioning_proportionality()
    {
        // Insert cases with/without proportionality mentions
        $this->agent->persistCase([
            'case_number' => 'K-701/2025',
            'proportionality_mentioned' => true,
            'extraction_confidence' => 0.90,
        ]);
        $this->agent->persistCase([
            'case_number' => 'K-702/2025',
            'proportionality_mentioned' => false,
            'extraction_confidence' => 0.90,
        ]);

        $proportionalityCases = HomeSearchCase::where('proportionality_mentioned', true)->get();

        $this->assertCount(1, $proportionalityCases);
        $this->assertEquals('K-701/2025', $proportionalityCases->first()->case_number);
    }
}
