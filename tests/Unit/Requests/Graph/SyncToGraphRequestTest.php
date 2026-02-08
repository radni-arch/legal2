<?php

namespace Tests\Unit\Requests\Graph;

use App\Http\Requests\Graph\SyncToGraphRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class SyncToGraphRequestTest extends TestCase
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
        $request = new SyncToGraphRequest;
        $validator = Validator::make([
            'entity_type' => 'law',
            'entity_id' => 'zkp-001',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new SyncToGraphRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new SyncToGraphRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new SyncToGraphRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new SyncToGraphRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
