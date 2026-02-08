<?php

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class RecontextualizeEvidenceRequest extends FormRequest
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
            'prosecution_narrative' => ['nullable', 'string', 'max:10000'],
            'alternative_context' => ['nullable', 'string', 'max:10000'],
            'focus_areas' => ['nullable', 'array'],
            'focus_areas.*' => ['string', 'max:255'],
            'include_precedents' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for evidence recontextualization.',
            'case_id.exists' => 'The specified case does not exist.',
            'evidence_text.required' => 'Evidence text cannot be empty.',
            'evidence_text.min' => 'Evidence text must be at least 10 characters.',
            'evidence_text.max' => 'Evidence text cannot exceed 50,000 characters.',
            'prosecution_narrative.max' => 'Prosecution narrative cannot exceed 10,000 characters.',
            'alternative_context.max' => 'Alternative context cannot exceed 10,000 characters.',
            'focus_areas.*.string' => 'Each focus area must be a string.',
        ];
    }
}
