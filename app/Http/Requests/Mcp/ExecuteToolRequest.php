<?php

namespace App\Http\Requests\Mcp;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:255'],
            'arguments' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tool_name.required' => 'Tool name is required to execute MCP tool.',
            'arguments.required' => 'Arguments array is required.',
        ];
    }
}
