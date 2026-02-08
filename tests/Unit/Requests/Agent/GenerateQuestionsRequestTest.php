<?php

namespace Tests\Unit\Requests\Agent;

use App\Http\Requests\Agent\GenerateQuestionsRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GenerateQuestionsRequestTest extends TestCase
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
            'title' => 'Test Case for Question Generation Validation',
            'user_id' => $user->id,
        ]);
        $this->testCaseId = $case->id;
    }

    /** @test */
    public function it_passes_validation_with_valid_required_fields()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'The defendant was stopped at a checkpoint without reasonable suspicion.',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_passes_validation_with_all_optional_fields()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'Police conducted a warrantless search of the defendant\'s home based on anonymous tip.',
            'question_types' => ['factual', 'legal', 'procedural'],
            'count' => 10,
            'difficulty' => 'medium',
            'focus_on' => ['Fourth Amendment', 'warrantless search'],
            'exclude_topics' => ['Miranda rights', 'self-incrimination'],
            'include_answers' => true,
            'include_citations' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'context' => 'Valid context for question generation.',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_context_is_missing()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_context_is_too_short()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'Too short',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('context', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_question_type_is_invalid()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'Valid context that is long enough for question generation.',
            'question_types' => ['factual', 'invalid_type'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('question_types.1', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_count_exceeds_maximum()
    {
        $request = new GenerateQuestionsRequest;
        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'Valid context that is long enough for question generation.',
            'count' => 25,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('count', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_question_types()
    {
        $request = new GenerateQuestionsRequest;
        $validTypes = ['factual', 'legal', 'procedural', 'strategic', 'evidentiary'];

        $validator = Validator::make([
            'case_id' => '01H0000000000000000000000',
            'context' => 'Valid context that is long enough for question generation.',
            'question_types' => $validTypes,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }
}
