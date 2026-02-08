<?php

namespace Tests\Unit\Requests\Odluke;

use App\Http\Requests\Odluke\GetOdlukeStatusRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GetOdlukeStatusRequestTest extends TestCase
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
        $request = new GetOdlukeStatusRequest;
        $validator = Validator::make([
            'job_id' => 'job-12345',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new GetOdlukeStatusRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new GetOdlukeStatusRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new GetOdlukeStatusRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new GetOdlukeStatusRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
