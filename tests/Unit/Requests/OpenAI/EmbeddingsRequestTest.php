<?php

namespace Tests\Unit\Requests\OpenAI;

use App\Http\Requests\OpenAI\EmbeddingsRequest;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EmbeddingsRequestTest extends TestCase
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
        $request = new EmbeddingsRequest;
        $validator = Validator::make([
            'input' => 'Legal text to embed',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_fails_validation_when_required_fields_missing()
    {
        $request = new EmbeddingsRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new EmbeddingsRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /** @test */
    public function it_has_authorization_method()
    {
        $request = new EmbeddingsRequest;
        $this->assertTrue(method_exists($request, 'authorize'));
    }

    /** @test */
    public function it_has_custom_messages()
    {
        $request = new EmbeddingsRequest;
        $messages = $request->messages();
        $this->assertIsArray($messages);
    }
}
