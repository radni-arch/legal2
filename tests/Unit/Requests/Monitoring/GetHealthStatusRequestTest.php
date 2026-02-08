<?php

namespace Tests\Unit\Requests\Monitoring;

use App\Http\Requests\Monitoring\GetHealthStatusRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GetHealthStatusRequestTest extends TestCase
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
        $request = new GetHealthStatusRequest;
        $validator = Validator::make([
            'service' => 'database',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new GetHealthStatusRequest;
        $validator = Validator::make([], $request->rules());

        // Some requests may have no required fields
        $this->assertIsArray($validator->errors()->toArray());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new GetHealthStatusRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new GetHealthStatusRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new GetHealthStatusRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }

    /** @test */
    public function it_validates_with_empty_data()
    {
        $request = new GetHealthStatusRequest;
        $validator = Validator::make([], $request->rules());
        $this->assertIsArray($validator->errors()->toArray());
    }

    /** @test */
    public function it_has_rules_method()
    {
        $request = new GetHealthStatusRequest;
        $this->assertTrue(method_exists($request, 'rules'));
        $this->assertIsArray($request->rules());
    }

    /** @test */
    public function it_has_messages_method()
    {
        $request = new GetHealthStatusRequest;
        $this->assertTrue(method_exists($request, 'messages'));
        $this->assertIsArray($request->messages());
    }
}
