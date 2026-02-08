<?php

namespace App\Http\Requests\Misconduct;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDismissalMotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'misconduct_findings' => ['required', 'array', 'min:1'],
            'misconduct_findings.*.type' => ['required', 'string'],
            'misconduct_findings.*.severity_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'misconduct_findings.*.description' => ['required', 'string', 'max:2000'],
            'misconduct_findings.*.evidence' => ['nullable', 'array'],
            'legal_standards' => ['nullable', 'array'],
            'legal_standards.*' => ['string', 'max:1000'],
            'remedies_requested' => ['nullable', 'array'],
            'remedies_requested.*' => ['string', 'in:dismissal,suppression,sanctions,recusal,new_trial'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for dismissal motion generation.',
            'case_id.exists' => 'The specified case does not exist.',
            'misconduct_findings.required' => 'At least one misconduct finding is required.',
            'misconduct_findings.min' => 'At least one misconduct finding is required.',
            'misconduct_findings.*.type.required' => 'Misconduct type is required for each finding.',
            'misconduct_findings.*.severity_score.required' => 'Severity score is required for each finding.',
            'remedies_requested.*.in' => 'Each remedy must be one of: dismissal, suppression, sanctions, recusal, new_trial.',
        ];
    }
}
