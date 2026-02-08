<?php

namespace Tests\Unit\Requests\Misconduct;

use App\Http\Requests\Misconduct\GenerateDismissalMotionRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GenerateDismissalMotionRequestTest extends TestCase
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
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [
                [
                    'type' => 'brady_violation',
                    'severity_score' => 85,
                    'description' => 'Failure to disclose exculpatory evidence',
                ],
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [
                [
                    'type' => 'brady_violation',
                    'severity_score' => 90,
                    'description' => 'Withheld exculpatory witness statements',
                    'evidence' => ['Document A', 'Document B'],
                ],
            ],
            'legal_standards' => ['Brady v. Maryland standard'],
            'remedies_requested' => ['dismissal', 'sanctions'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'misconduct_findings' => [['type' => 'brady', 'severity_score' => 80, 'description' => 'Test']],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_misconduct_findings_is_missing()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('misconduct_findings', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_misconduct_findings_is_empty()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('misconduct_findings', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_finding_missing_required_fields()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [
                ['type' => 'brady_violation'],
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_remedy_is_invalid()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [
                ['type' => 'brady', 'severity_score' => 80, 'description' => 'Test'],
            ],
            'remedies_requested' => ['invalid_remedy'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('remedies_requested.0', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_remedies()
    {
        $request = new GenerateDismissalMotionRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'misconduct_findings' => [
                ['type' => 'brady', 'severity_score' => 80, 'description' => 'Test'],
            ],
            'remedies_requested' => ['dismissal', 'suppression', 'sanctions', 'recusal', 'new_trial'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
