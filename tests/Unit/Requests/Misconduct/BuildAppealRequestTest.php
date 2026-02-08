<?php

namespace Tests\Unit\Requests\Misconduct;

use App\Http\Requests\Misconduct\BuildAppealRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class BuildAppealRequestTest extends TestCase
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
        $request = new BuildAppealRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'trial_court_ruling' => 'Court ruled in favor of prosecution',
            'misconduct_issues' => [[
                'type' => 'brady_violation',
                'description' => 'Withheld evidence',
                'preserved' => true,
            ]],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new BuildAppealRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new BuildAppealRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new BuildAppealRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new BuildAppealRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
