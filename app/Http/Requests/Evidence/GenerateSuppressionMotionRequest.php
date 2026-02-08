<?php

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSuppressionMotionRequest extends FormRequest
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
            'violation_type' => ['required', 'string', 'in:fourth_amendment,unlawful_search,Miranda_violation,illegal_seizure,chain_of_custody,fruit_of_poisonous_tree'],
            'facts' => ['required', 'string', 'min:50', 'max:10000'],
            'legal_basis' => ['nullable', 'array'],
            'legal_basis.*' => ['string', 'max:1000'],
            'precedents' => ['nullable', 'array'],
            'precedents.*.case_name' => ['required_with:precedents', 'string', 'max:500'],
            'precedents.*.citation' => ['required_with:precedents', 'string', 'max:500'],
            'precedents.*.relevance' => ['nullable', 'string', 'max:1000'],
            'relief_sought' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for generating suppression motion.',
            'case_id.exists' => 'The specified case does not exist.',
            'evidence_text.required' => 'Evidence description cannot be empty.',
            'evidence_text.min' => 'Evidence description must be at least 10 characters.',
            'evidence_text.max' => 'Evidence description cannot exceed 50,000 characters.',
            'violation_type.required' => 'Violation type is required.',
            'violation_type.in' => 'Invalid violation type specified.',
            'facts.required' => 'Facts of the case are required.',
            'facts.min' => 'Facts must be at least 50 characters.',
            'facts.max' => 'Facts cannot exceed 10,000 characters.',
            'precedents.*.case_name.required_with' => 'Case name is required for each precedent.',
            'precedents.*.citation.required_with' => 'Citation is required for each precedent.',
        ];
    }
}
