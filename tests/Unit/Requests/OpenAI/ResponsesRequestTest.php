<?php

namespace Tests\Unit\Requests\OpenAI;

use App\Http\Requests\OpenAI\ResponsesRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ResponsesRequestTest extends TestCase
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
        $request = new ResponsesRequest;
        $validator = Validator::make([
            'prompt' => 'Generate legal analysis',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new ResponsesRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new ResponsesRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new ResponsesRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new ResponsesRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
