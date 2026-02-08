<?php

namespace App\Http\Requests\OpenAI;

use Illuminate\Foundation\Http\FormRequest;

class ResponsesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // API token middleware handles auth
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'in:gpt-4o,gpt-4o-mini,gpt-4-turbo,gpt-3.5-turbo'],
            'system' => ['nullable', 'string', 'max:5000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:16000'],
            'response_format' => ['nullable', 'array'],
            'response_format.type' => ['nullable', 'string', 'in:text,json_object'],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Prompt is required.',
            'prompt.max' => 'Prompt cannot exceed 10,000 characters.',
            'model.in' => 'Model must be one of: gpt-4o, gpt-4o-mini, gpt-4-turbo, gpt-3.5-turbo.',
            'system.max' => 'System message cannot exceed 5,000 characters.',
            'response_format.type.in' => 'Response format type must be either text or json_object.',
        ];
    }
}
