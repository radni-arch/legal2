<?php

namespace App\Http\Requests\Misconduct;

use Illuminate\Foundation\Http\FormRequest;

class GenerateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'prosecutor_name' => ['required', 'string', 'max:255'],
            'prosecutor_office' => ['required', 'string', 'max:255'],
            'misconduct_findings' => ['required', 'array', 'min:1'],
            'misconduct_findings.*.type' => ['required', 'string'],
            'misconduct_findings.*.description' => ['required', 'string', 'max:2000'],
            'misconduct_findings.*.date' => ['nullable', 'date'],
            'violations' => ['nullable', 'array'],
            'violations.*' => ['string', 'max:500'],
            'complaint_type' => ['required', 'string', 'in:ethics,disciplinary,criminal_referral'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for complaint generation.',
            'prosecutor_name.required' => 'Prosecutor name is required.',
            'prosecutor_office.required' => 'Prosecutor office is required.',
            'misconduct_findings.required' => 'At least one misconduct finding is required.',
            'complaint_type.required' => 'Complaint type is required.',
            'complaint_type.in' => 'Complaint type must be one of: ethics, disciplinary, criminal_referral.',
        ];
    }
}
