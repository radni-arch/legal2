<?php

namespace Tests\Unit\Requests\Agent;

use App\Http\Requests\Agent\StartResearchRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StartResearchRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case for validation
        $user = User::first() ?? User::factory()->create();
        $case = LegalCase::create([
            'id' => '01H0000000000000000000000',
            'title' => 'Test Case for Research Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'research_topic' => 'Analysis of proportionality in home search warrants under Croatian law',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'research_topic' => 'Fourth Amendment jurisprudence on reasonable suspicion',
            'focus_areas' => ['vehicle searches', 'plain view doctrine'],
            'depth' => 'deep',
            'max_time_minutes' => 60,
            'include_decisions' => true,
            'include_laws' => true,
            'include_precedents' => true,
            'jurisdictions' => ['Županijski sud u Osijeku', 'Vrhovni sud RH'],
            'date_range' => [
                'from' => '2020-01-01',
                'to' => '2024-12-31',
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'research_topic' => 'Test research topic',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_research_topic_is_missing()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('research_topic', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_research_topic_is_too_short()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'research_topic' => 'Short',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('research_topic', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_depth_is_invalid()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'research_topic' => 'Valid research topic that is long enough',
            'depth' => 'invalid_depth',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('depth', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_date_range_end_is_before_start()
    {
        $request = new StartResearchRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'research_topic' => 'Valid research topic that is long enough',
            'date_range' => [
                'from' => '2024-12-31',
                'to' => '2024-01-01',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_range.to', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_depth_levels()
    {
        $request = new StartResearchRequest;
        $validDepths = ['shallow', 'medium', 'deep'];

        foreach ($validDepths as $depth) {
            $validator = Validator::make([
                'case_id' => '01H0000000000000000000000',
                'research_topic' => 'Valid research topic that is long enough',
                'depth' => $depth,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Depth level {$depth} should be valid");
        }
    }
}
