<?php

namespace Tests\Unit\Requests\Evidence;

use App\Http\Requests\Evidence\CheckAdmissibilityRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CheckAdmissibilityRequestTest extends TestCase
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
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description that is long enough.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_chain_of_custody()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description that is long enough.',
            'chain_of_custody' => [
                [
                    'handler' => 'Officer John Doe',
                    'timestamp' => '2024-01-15 10:30:00',
                    'action' => 'Evidence collected at crime scene',
                ],
                [
                    'handler' => 'Forensic Technician Jane Smith',
                    'timestamp' => '2024-01-15 14:00:00',
                    'action' => 'Evidence logged into evidence room',
                ],
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'evidence_text' => 'This is valid evidence description.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_missing()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_chain_of_custody_handler_is_missing()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description.',
            'chain_of_custody' => [
                [
                    'timestamp' => '2024-01-15 10:30:00',
                    'action' => 'Evidence collected',
                ],
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('chain_of_custody.0.handler', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_chain_of_custody_timestamp_is_invalid()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description.',
            'chain_of_custody' => [
                [
                    'handler' => 'Officer John Doe',
                    'timestamp' => 'invalid-date',
                    'action' => 'Evidence collected',
                ],
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('chain_of_custody.0.timestamp', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_warrant_present_flag()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence description.',
            'warrant_present' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_exceeds_max_length()
    {
        $request = new CheckAdmissibilityRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => str_repeat('a', 50001),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }
}
