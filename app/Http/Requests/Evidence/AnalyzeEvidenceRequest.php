<?php

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeEvidenceRequest extends FormRequest
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
            'context' => ['nullable', 'array'],
            'context.date' => ['nullable', 'date'],
            'context.location' => ['nullable', 'string', 'max:500'],
            'analysis_type' => ['nullable', 'string', 'in:admissibility,recontextualization,suppression'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for evidence analysis.',
            'case_id.exists' => 'The specified case does not exist.',
            'evidence_text.required' => 'Evidence text cannot be empty.',
            'evidence_text.min' => 'Evidence text must be at least 10 characters.',
            'evidence_text.max' => 'Evidence text cannot exceed 50,000 characters.',
        ];
    }
}
