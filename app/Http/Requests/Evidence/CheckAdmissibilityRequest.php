<?php

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class CheckAdmissibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'evidence_text' => ['required', 'string', 'min:10', 'max:50000'],
            'evidence_type' => ['nullable', 'string', 'max:100'],
            'collection_method' => ['nullable', 'string', 'max:500'],
            'chain_of_custody' => ['nullable', 'array'],
            'chain_of_custody.*.handler' => ['required_with:chain_of_custody', 'string', 'max:255'],
            'chain_of_custody.*.timestamp' => ['required_with:chain_of_custody', 'date'],
            'chain_of_custody.*.action' => ['required_with:chain_of_custody', 'string', 'max:255'],
            'warrant_present' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for admissibility check.',
            'case_id.exists' => 'The specified case does not exist.',
            'evidence_text.required' => 'Evidence description cannot be empty.',
            'evidence_text.min' => 'Evidence description must be at least 10 characters.',
            'evidence_text.max' => 'Evidence description cannot exceed 50,000 characters.',
            'chain_of_custody.*.handler.required_with' => 'Handler name is required for each custody entry.',
            'chain_of_custody.*.timestamp.required_with' => 'Timestamp is required for each custody entry.',
            'chain_of_custody.*.action.required_with' => 'Action is required for each custody entry.',
        ];
    }
}
