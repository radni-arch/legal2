<?php

namespace Tests\Unit\Requests\Misconduct;

use App\Http\Requests\Misconduct\AnalyzeMisconductRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyzeMisconductRequestTest extends TestCase
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
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'context' => 'Evidence suggests Brady violation in prosecution case',
            'evidence_texts' => ['Text 1', 'Text 2'],
            'focus_areas' => ['brady_violation', 'witness_tampering'],
            'threshold' => 75,
            'include_precedents' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_case_id_does_not_exist()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => '01H9999999999999999999999',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_focus_area_is_invalid()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'focus_areas' => ['invalid_focus'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('focus_areas.0', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_threshold_exceeds_maximum()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'threshold' => 150,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('threshold', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_focus_areas()
    {
        $request = new AnalyzeMisconductRequest;
        $validFocusAreas = ['brady_violation', 'witness_tampering', 'evidence_fabrication',
            'selective_prosecution', 'coercive_interrogation', 'undisclosed_deals'];

        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'focus_areas' => $validFocusAreas,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_context_exceeds_max_length()
    {
        $request = new AnalyzeMisconductRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'context' => str_repeat('a', 10001),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }
}
