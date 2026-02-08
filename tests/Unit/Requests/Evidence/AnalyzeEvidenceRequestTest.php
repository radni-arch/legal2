<?php

namespace Tests\Unit\Requests\Evidence;

use App\Http\Requests\Evidence\AnalyzeEvidenceRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyzeEvidenceRequestTest extends TestCase
{
    use UsesTestDatabase;

    private string $testCaseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case for validation
        $user = User::first() ?? User::factory()->create();
        $case = LegalCase::create([
            'id' => '01H0000000000000000000000',
            'title' => 'Test Case for Evidence Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text that is long enough.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text that is long enough.',
            'context' => [
                'date' => '2024-01-15',
                'location' => 'Osijek, Croatia',
            ],
            'analysis_type' => 'admissibility',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'evidence_text' => 'This is valid evidence text that is long enough.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_missing()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_too_short()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'short',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_too_long()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => str_repeat('a', 50001),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_case_id_does_not_exist()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => 99999,
            'evidence_text' => 'This is valid evidence text that is long enough.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_analysis_type_is_invalid()
    {
        $request = new AnalyzeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text that is long enough.',
            'analysis_type' => 'invalid_type',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('analysis_type', $validator->errors()->toArray());
    }
}
