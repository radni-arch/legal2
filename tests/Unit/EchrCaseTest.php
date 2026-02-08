<?php

namespace Tests\Unit;

use App\Models\EchrCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EchrCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_hudoc_url_is_generated_correctly(): void
    {
        $case = new EchrCase(['item_id' => '001-123456']);

        $this->assertEquals(
            'https://hudoc.echr.coe.int/eng?i=001-123456',
            $case->hudoc_url
        );
    }

    public function test_short_name_fallback(): void
    {
        $case = new EchrCase([
            'case_name' => 'CASE OF SMITH v. COUNTRY',
            'case_name_short' => null,
        ]);

        $this->assertEquals('CASE OF SMITH v. COUNTRY', $case->short_name);

        $case->case_name_short = 'Smith v. Country';
        $this->assertEquals('Smith v. Country', $case->short_name);
    }

    public function test_violations_are_cast_to_array(): void
    {
        $case = EchrCase::factory()->create([
            'violations' => ['6', '8', 'P1-1'],
        ]);

        $this->assertIsArray($case->violations);
        $this->assertContains('6', $case->violations);
    }

    public function test_croatia_scope(): void
    {
        EchrCase::factory()->create(['respondent_state' => 'Croatia']);
        EchrCase::factory()->create(['respondent_state' => 'Poland']);

        $croatian = EchrCase::croatia()->get();

        $this->assertCount(1, $croatian);
        $this->assertEquals('Croatia', $croatian->first()->respondent_state);
    }

    public function test_court_case_searchable_interface(): void
    {
        $case = EchrCase::factory()->create([
            'item_id' => '001-999999',
            'application_number' => '12345/20',
            'case_name' => 'CASE OF TEST v. CROATIA',
            'case_name_short' => 'Test v. Croatia',
            'respondent_state' => 'Croatia',
            'judgment_date' => '2024-06-15',
        ]);

        $this->assertEquals('12345/20', $case->getCaseIdentifier());
        $this->assertEquals('Test v. Croatia', $case->getCaseName());
        $this->assertEquals('European Court of Human Rights', $case->getCourtName());
        $this->assertEquals('2024-06-15', $case->getDecisionDate());
        $this->assertEquals('https://hudoc.echr.coe.int/eng?i=001-999999', $case->getSourceUrl());

        $searchable = $case->toSearchableArray();
        $this->assertEquals('echr', $searchable['type']);
        $this->assertEquals('Croatia', $searchable['state']);
    }
}
