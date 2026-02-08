<?php

namespace App\Http\Requests\OpenAI;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // API token middleware handles auth
    }

    public function rules(): array
    {
        return [
            'model' => ['nullable', 'string', 'in:gpt-4o,gpt-4o-mini,gpt-4-turbo,gpt-3.5-turbo'],
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:system,user,assistant,function'],
            'messages.*.content' => ['required', 'string', 'max:50000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:16000'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'stream' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'model.in' => 'Model must be one of: gpt-4o, gpt-4o-mini, gpt-4-turbo, gpt-3.5-turbo.',
            'messages.required' => 'At least one message is required.',
            'messages.*.role.in' => 'Message role must be one of: system, user, assistant, function.',
            'messages.*.content.max' => 'Message content cannot exceed 50,000 characters.',
            'temperature.min' => 'Temperature must be between 0 and 2.',
            'temperature.max' => 'Temperature must be between 0 and 2.',
            'max_tokens.max' => 'Max tokens cannot exceed 16,000.',
        ];
    }
}
