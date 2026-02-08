<?php

namespace Tests\Unit\Requests\OpenAI;

use App\Http\Requests\OpenAI\ChatRequest;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ChatRequestTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_has_validation_rules()
    {
        $request = new ChatRequest;
        $rules = $request->rules();
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }
}
