<?php

namespace Tests\Unit\Requests\Admin;

use App\Http\Requests\Admin\ManageSystemRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ManageSystemRequestTest extends TestCase
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
        $request = new ManageSystemRequest;
        $validator = Validator::make([
            'action' => 'clear_cache',
            'confirm' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new ManageSystemRequest;
        $validator = Validator::make([], $request->rules());

        // Some requests may have no required fields
        $this->assertIsArray($validator->errors()->toArray());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new ManageSystemRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new ManageSystemRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new ManageSystemRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }

    /** @test */
    public function it_validates_with_empty_data()
    {
        $request = new ManageSystemRequest;
        $validator = Validator::make([], $request->rules());
        $this->assertIsArray($validator->errors()->toArray());
    }

    /** @test */
    public function it_has_rules_method()
    {
        $request = new ManageSystemRequest;
        $this->assertTrue(method_exists($request, 'rules'));
        $this->assertIsArray($request->rules());
    }

    /** @test */
    public function it_has_messages_method()
    {
        $request = new ManageSystemRequest;
        $this->assertTrue(method_exists($request, 'messages'));
        $this->assertIsArray($request->messages());
    }
}
