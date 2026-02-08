<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeReasoningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'argument' => ['required', 'string', 'min:50', 'max:10000'],
            'reasoning_type' => ['nullable', 'string', 'in:deductive,inductive,analogical,abductive'],
            'check_fallacies' => ['nullable', 'boolean'],
            'suggest_improvements' => ['nullable', 'boolean'],
            'include_precedents' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for reasoning analysis.',
            'argument.required' => 'Legal argument is required.',
            'argument.min' => 'Argument must be at least 50 characters.',
            'argument.max' => 'Argument cannot exceed 10,000 characters.',
            'reasoning_type.in' => 'Reasoning type must be one of: deductive, inductive, analogical, abductive.',
        ];
    }
}
