<?php

namespace App\Http\Requests\Odluke;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteOdlukeAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:2000'],
            'context' => ['nullable', 'array'],
            'async' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required for Odluke agent execution.',
            'query.min' => 'Query must be at least 3 characters.',
            'query.max' => 'Query cannot exceed 2000 characters.',
        ];
    }
}
