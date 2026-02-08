<?php

namespace App\Http\Requests\Topic;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'topic' => ['required', 'string', 'in:drug_charge_severity,home_search_abuse,bail_denial,pretrial_detention,witness_intimidation'],
            'context' => ['nullable', 'string', 'max:10000'],
            'parameters' => ['nullable', 'array'],
            'parameters.threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'parameters.include_precedents' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for topic analysis.',
            'case_id.exists' => 'The specified case does not exist.',
            'topic.required' => 'Topic is required.',
            'topic.in' => 'Topic must be one of: drug_charge_severity, home_search_abuse, bail_denial, pretrial_detention, witness_intimidation.',
            'context.max' => 'Context cannot exceed 10,000 characters.',
        ];
    }
}
