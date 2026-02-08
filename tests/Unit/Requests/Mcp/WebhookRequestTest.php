<?php

namespace Tests\Unit\Requests\Mcp;

use App\Http\Requests\Mcp\WebhookRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class WebhookRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new WebhookRequest;

        // Simulate prepareForValidation
        if (isset($data['name']) && ! isset($data['function_name'])) {
            $data['function_name'] = $data['name'];
        }

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_valid_with_function_name(): void
    {
        $data = [
            'function_name' => 'search_laws',
            'arguments' => ['query' => 'test'],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_name(): void
    {
        $data = [
            'name' => 'search_laws',
            'arguments' => ['query' => 'test'],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_both_name_and_function_name(): void
    {
        $data = [
            'function_name' => 'search_laws',
            'name' => 'search_laws',
            'arguments' => ['query' => 'test'],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_without_arguments(): void
    {
        $data = [
            'function_name' => 'list_all_laws',
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_empty_arguments(): void
    {
        $data = [
            'function_name' => 'list_all_laws',
            'arguments' => [],
        ];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_function_name_required_without_name(): void
    {
        $data = [
            'arguments' => ['query' => 'test'],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertTrue(
            $validator->errors()->has('function_name') ||
            $validator->errors()->has('name')
        );
    }

    public function test_function_name_max_length(): void
    {
        $data = [
            'function_name' => str_repeat('a', 256),
            'arguments' => [],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('function_name', $validator->errors()->toArray());
    }

    public function test_name_max_length(): void
    {
        $data = [
            'name' => str_repeat('a', 256),
            'arguments' => [],
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        // After prepareForValidation, 'name' becomes 'function_name'
        $this->assertTrue(
            $validator->errors()->has('function_name') ||
            $validator->errors()->has('name')
        );
    }

    public function test_arguments_must_be_array(): void
    {
        $data = [
            'function_name' => 'search_laws',
            'arguments' => 'not an array',
        ];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('arguments', $validator->errors()->toArray());
    }

    public function test_authorization_always_true(): void
    {
        $request = new WebhookRequest;
        $this->assertTrue($request->authorize());
    }
}
