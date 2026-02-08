<?php

namespace App\Http\Requests\Mcp;

use App\Http\Requests\Concerns\HasCommonValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ChatCompletionsRequest extends FormRequest
{
    use HasCommonValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1'],
            'messages.*' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:system,user,assistant,tool'],
            'messages.*.content' => ['nullable', 'string', 'max:50000'],
            'messages.*.name' => ['nullable', 'string', 'max:100'],
            'messages.*.tool_calls' => ['nullable', 'array'],
            'messages.*.tool_call_id' => ['nullable', 'string', 'max:100'],

            'model' => ['nullable', 'string', 'max:100'],
            'tools' => ['nullable', 'array'],
            'tool_choice' => ['nullable', 'string|array'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'stop' => ['nullable', 'string|array'],
            'n' => ['nullable', 'integer', 'min:1', 'max:10'],
            'stream' => ['nullable', 'boolean'],
            'user' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'messages.required' => 'At least one message is required.',
            'messages.*.role.in' => 'Message role must be one of: system, user, assistant, tool.',
            'messages.*.content.max' => 'Message content cannot exceed 50,000 characters.',
            'model.max' => 'Model name cannot exceed 100 characters.',
            'temperature.between' => 'Temperature must be between 0 and 2.',
            'max_tokens.min' => 'Max tokens must be at least 1.',
            'max_tokens.max' => 'Max tokens cannot exceed 100,000.',
        ];
    }
}
