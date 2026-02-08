<?php

namespace Tests\Unit\Requests\Agent;

use App\Http\Requests\Agent\RunAgentRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RunAgentRequestTest extends TestCase
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
            'title' => 'Test Case for Agent Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'research',
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'decision_discovery',
            'case_id' => '01H0000000000000000000000',
            'parameters' => [
                'topic' => 'Fourth Amendment search and seizure',
                'focus_areas' => ['proportionality', 'reasonable suspicion'],
            ],
            'max_iterations' => 5,
            'max_time_seconds' => 1800,
            'cost_budget_usd' => 2.5,
            'async' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_agent_type_is_missing()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('agent_type', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'research',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_agent_type_is_invalid()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'invalid_agent',
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('agent_type', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_max_iterations_exceeds_limit()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'research',
            'case_id' => '01H0000000000000000000000',
            'max_iterations' => 15,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('max_iterations', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_cost_budget_exceeds_limit()
    {
        $request = new RunAgentRequest;
        $validator = Validator::make([
            'agent_type' => 'research',
            'case_id' => '01H0000000000000000000000',
            'cost_budget_usd' => 15.0,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cost_budget_usd', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_agent_types()
    {
        $request = new RunAgentRequest;
        $validTypes = ['research', 'decision_discovery', 'odluke', 'question_generator'];

        foreach ($validTypes as $type) {
            $validator = Validator::make([
                'agent_type' => $type,
                'case_id' => '01H0000000000000000000000',
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Agent type {$type} should be valid");
        }
    }
}
