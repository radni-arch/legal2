<?php

namespace App\Http\Requests\Misconduct;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeMisconductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'context' => ['nullable', 'string', 'max:10000'],
            'evidence_texts' => ['nullable', 'array'],
            'evidence_texts.*' => ['string', 'max:5000'],
            'focus_areas' => ['nullable', 'array'],
            'focus_areas.*' => ['string', 'in:brady_violation,witness_tampering,evidence_fabrication,selective_prosecution,coercive_interrogation,undisclosed_deals'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'include_precedents' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for misconduct analysis.',
            'case_id.exists' => 'The specified case does not exist.',
            'context.max' => 'Context cannot exceed 10,000 characters.',
            'evidence_texts.*.max' => 'Each evidence text cannot exceed 5,000 characters.',
            'focus_areas.*.in' => 'Focus area must be one of: brady_violation, witness_tampering, evidence_fabrication, selective_prosecution, coercive_interrogation, undisclosed_deals.',
            'threshold.min' => 'Threshold must be between 0 and 100.',
            'threshold.max' => 'Threshold must be between 0 and 100.',
        ];
    }
}
