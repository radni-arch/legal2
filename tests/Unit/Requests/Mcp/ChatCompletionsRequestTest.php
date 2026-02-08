<?php

namespace Tests\Unit\Requests\Mcp;

use App\Http\Requests\Mcp\ChatCompletionsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ChatCompletionsRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new ChatCompletionsRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_valid_basic_chat_request(): void
    {
        $data = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_multi_message_chat(): void
    {
        $data = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
                ['role' => 'assistant', 'content' => 'Hi! How can I help?'],
                ['role' => 'user', 'content' => 'Tell me about Laravel.'],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_all_optional_parameters(): void
    {
        $data = [
            'messages' => [
                ['role' => 'user', 'content' => 'Test'],
            ],
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.3,
            'n' => 1,
            'stream' => false,
            'user' => 'user-123',
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_tool_calls(): void
    {
        $data = [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_laws',
                                'arguments' => '{"query": "kazneni zakon"}',
                            ],
                        ],
                    ],
                ],
                [
                    'role' => 'tool',
                    'tool_call_id' => 'call_123',
                    'content' => 'Found 10 laws',
                ],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_messages_required(): void
    {
        $data = [];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages', $validator->errors()->toArray());
    }

    public function test_messages_must_be_array(): void
    {
        $data = ['messages' => 'not an array'];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages', $validator->errors()->toArray());
    }

    public function test_messages_must_have_at_least_one_message(): void
    {
        $data = ['messages' => []];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages', $validator->errors()->toArray());
    }

    public function test_message_role_required(): void
    {
        $data = [
            'messages' => [
                ['content' => 'Hello'],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages.0.role', $validator->errors()->toArray());
    }

    public function test_message_role_must_be_valid(): void
    {
        $data = [
            'messages' => [
                ['role' => 'invalid_role', 'content' => 'Hello'],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages.0.role', $validator->errors()->toArray());
    }

    public function test_content_max_length(): void
    {
        $data = [
            'messages' => [
                ['role' => 'user', 'content' => str_repeat('a', 50001)],
            ],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('messages.0.content', $validator->errors()->toArray());
    }

    public function test_temperature_bounds(): void
    {
        $invalidTemps = [-0.1, 2.1, 3.0];

        foreach ($invalidTemps as $temp) {
            $data = [
                'messages' => [['role' => 'user', 'content' => 'Test']],
                'temperature' => $temp,
            ];

            $validator = $this->validate($data);
            $this->assertFalse($validator->passes(), "Temperature {$temp} should be invalid");
            $this->assertArrayHasKey('temperature', $validator->errors()->toArray());
        }
    }

    public function test_max_tokens_bounds(): void
    {
        $data = [
            'messages' => [['role' => 'user', 'content' => 'Test']],
            'max_tokens' => 0,
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('max_tokens', $validator->errors()->toArray());

        $data['max_tokens'] = 100001;
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('max_tokens', $validator->errors()->toArray());
    }

    public function test_top_p_bounds(): void
    {
        $data = [
            'messages' => [['role' => 'user', 'content' => 'Test']],
            'top_p' => 1.1,
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('top_p', $validator->errors()->toArray());
    }

    public function test_n_bounds(): void
    {
        $data = [
            'messages' => [['role' => 'user', 'content' => 'Test']],
            'n' => 11,
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('n', $validator->errors()->toArray());
    }

    public function test_authorization_always_true(): void
    {
        $request = new ChatCompletionsRequest;
        $this->assertTrue($request->authorize());
    }
}
