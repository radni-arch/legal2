<?php

namespace Tests\Unit\Requests\Odluke;

use App\Http\Requests\Odluke\ExecuteOdlukeAgentRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ExecuteOdlukeAgentRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        $case = LegalCase::create([
            'title' => 'Test Case',
            'client_name' => 'John Doe',
            'status' => 'active',
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_data()
    {
        $request = new ExecuteOdlukeAgentRequest;
        $validator = Validator::make([
            'query' => 'Fourth Amendment search and seizure',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new ExecuteOdlukeAgentRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new ExecuteOdlukeAgentRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new ExecuteOdlukeAgentRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new ExecuteOdlukeAgentRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
