<?php

namespace Tests\Unit\Requests\Misconduct;

use App\Http\Requests\Misconduct\GenerateComplaintRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GenerateComplaintRequestTest extends TestCase
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
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [
                ['type' => 'brady_violation', 'description' => 'Failed to disclose evidence'],
            ],
            'complaint_type' => 'ethics',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [
                [
                    'type' => 'witness_tampering',
                    'description' => 'Intimidated defense witness',
                    'date' => '2025-01-15',
                ],
            ],
            'violations' => ['Professional Rules 3.8', 'ABA Model Rule 3.8'],
            'complaint_type' => 'disciplinary',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_prosecutor_name_is_missing()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [['type' => 'brady', 'description' => 'Test']],
            'complaint_type' => 'ethics',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('prosecutor_name', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_complaint_type_is_invalid()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [['type' => 'brady', 'description' => 'Test']],
            'complaint_type' => 'invalid_type',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('complaint_type', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_complaint_types()
    {
        $request = new GenerateComplaintRequest;
        $validTypes = ['ethics', 'disciplinary', 'criminal_referral'];

        foreach ($validTypes as $type) {
            $validator = Validator::make([
                'case_id' => $this->testCaseId,
                'prosecutor_name' => 'Jane Smith',
                'prosecutor_office' => 'State Attorney Office',
                'misconduct_findings' => [['type' => 'brady', 'description' => 'Test']],
                'complaint_type' => $type,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Complaint type {$type} should be valid");
        }
    }

    /** @test */
    public function it_fails_validation_when_case_id_does_not_exist()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => '01H9999999999999999999999',
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [['type' => 'brady', 'description' => 'Test']],
            'complaint_type' => 'ethics',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_misconduct_findings_is_empty()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [],
            'complaint_type' => 'ethics',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('misconduct_findings', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_finding_description_exceeds_max_length()
    {
        $request = new GenerateComplaintRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'prosecutor_name' => 'Jane Smith',
            'prosecutor_office' => 'State Attorney Office',
            'misconduct_findings' => [
                ['type' => 'brady', 'description' => str_repeat('a', 2001)],
            ],
            'complaint_type' => 'ethics',
        ], $request->rules());

        $this->assertTrue($validator->fails());
    }
}
