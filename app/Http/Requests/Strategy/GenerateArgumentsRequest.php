<?php

namespace App\Http\Requests\Strategy;

use Illuminate\Foundation\Http\FormRequest;

class GenerateArgumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'objectives' => ['required', 'array', 'min:1'],
            'objectives.*' => ['required', 'string', 'max:500'],
            'argument_type' => ['nullable', 'string', 'in:defensive,offensive,procedural,constitutional'],
            'include_precedents' => ['nullable', 'boolean'],
            'include_statutes' => ['nullable', 'boolean'],
            'max_arguments' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'objectives.required' => 'At least one objective is required to generate arguments.',
            'objectives.min' => 'Please provide at least one objective.',
            'objectives.*.required' => 'Each objective must have a description.',
            'objectives.*.max' => 'Each objective cannot exceed 500 characters.',
            'argument_type.in' => 'Argument type must be one of: defensive, offensive, procedural, constitutional.',
            'max_arguments.max' => 'Cannot generate more than 20 arguments at once.',
        ];
    }
}
