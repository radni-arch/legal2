<?php

namespace Tests\Unit\Requests\Topic;

use App\Http\Requests\Topic\AnalyzeTopicRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyzeTopicRequestTest extends TestCase
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
        $request = new AnalyzeTopicRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'topic' => 'drug_charge_severity',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_case_id_is_missing()
    {
        $request = new AnalyzeTopicRequest;
        $validator = Validator::make([
            'topic' => 'drug_charge_severity',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_fails_validation_when_topic_is_invalid()
    {
        $request = new AnalyzeTopicRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'topic' => 'invalid_topic',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('topic', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_all_valid_topics()
    {
        $request = new AnalyzeTopicRequest;
        $validTopics = ['drug_charge_severity', 'home_search_abuse', 'bail_denial',
            'pretrial_detention', 'witness_intimidation'];

        foreach ($validTopics as $topic) {
            $validator = Validator::make([
                'case_id' => $this->testCaseId,
                'topic' => $topic,
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Topic {$topic} should be valid");
        }
    }

    /** @test */
    public function it_fails_validation_when_case_id_does_not_exist()
    {
        $request = new AnalyzeTopicRequest;
        $validator = Validator::make([
            'case_id' => '01H9999999999999999999999',
            'topic' => 'drug_charge_severity',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('case_id', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_nested_parameters()
    {
        $request = new AnalyzeTopicRequest;
        $validator = Validator::make([
            'case_id' => $this->testCaseId,
            'topic' => 'drug_charge_severity',
            'parameters' => [
                'threshold' => 50,
                'include_precedents' => false,
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new AnalyzeTopicRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new AnalyzeTopicRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
