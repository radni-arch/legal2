<?php

namespace App\Http\Requests\Misconduct;

use Illuminate\Foundation\Http\FormRequest;

class BuildAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'trial_court_ruling' => ['required', 'string', 'max:10000'],
            'misconduct_issues' => ['required', 'array', 'min:1'],
            'misconduct_issues.*.type' => ['required', 'string'],
            'misconduct_issues.*.description' => ['required', 'string', 'max:2000'],
            'misconduct_issues.*.preserved' => ['required', 'boolean'],
            'standard_of_review' => ['nullable', 'string', 'in:de_novo,abuse_of_discretion,clearly_erroneous,plain_error'],
            'grounds' => ['nullable', 'array'],
            'grounds.*' => ['string', 'max:1000'],
            'relief_sought' => ['nullable', 'string', 'in:reversal,remand,new_trial,dismissal'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for appeal building.',
            'trial_court_ruling.required' => 'Trial court ruling is required.',
            'misconduct_issues.required' => 'At least one misconduct issue is required.',
            'misconduct_issues.*.preserved.required' => 'Preserved status is required for each issue.',
            'standard_of_review.in' => 'Standard of review must be one of: de_novo, abuse_of_discretion, clearly_erroneous, plain_error.',
            'relief_sought.in' => 'Relief sought must be one of: reversal, remand, new_trial, dismissal.',
        ];
    }
}
