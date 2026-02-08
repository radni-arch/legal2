<?php

namespace Tests\Unit\Requests\Evidence;

use App\Http\Requests\Evidence\RecontextualizeEvidenceRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class RecontextualizeEvidenceRequestTest extends TestCase
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
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text that needs recontextualization.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text that needs recontextualization.',
            'prosecution_narrative' => 'The prosecution claims the defendant acted with intent.',
            'alternative_context' => 'However, the evidence can be interpreted as self-defense.',
            'focus_areas' => ['intent', 'timeline', 'witness credibility'],
            'include_precedents' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'evidence_text' => 'This is valid evidence text.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_missing()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_evidence_text_is_too_short()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'short',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('evidence_text', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_prosecution_narrative_exceeds_max_length()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text.',
            'prosecution_narrative' => str_repeat('a', 10001),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('prosecution_narrative', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_focus_areas_array()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text.',
            'focus_areas' => ['intent', 'motive', 'opportunity'],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_alternative_context_exceeds_max_length()
    {
        $request = new RecontextualizeEvidenceRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'evidence_text' => 'This is valid evidence text.',
            'alternative_context' => str_repeat('a', 10001),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('alternative_context', $validator->errors()->toArray());
    }
}
