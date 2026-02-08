<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCounterargumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'prosecution_argument' => ['required', 'string', 'min:50', 'max:10000'],
            'focus_on' => ['nullable', 'array'],
            'focus_on.*' => ['string', 'in:facts,law,procedure,evidence,jurisdiction'],
            'strength' => ['nullable', 'string', 'in:weak,moderate,strong'],
            'include_precedents' => ['nullable', 'boolean'],
            'style' => ['nullable', 'string', 'in:aggressive,moderate,diplomatic'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for counterargument generation.',
            'prosecution_argument.required' => 'Prosecution argument is required.',
            'prosecution_argument.min' => 'Argument must be at least 50 characters.',
            'focus_on.*.in' => 'Focus area must be one of: facts, law, procedure, evidence, jurisdiction.',
            'strength.in' => 'Strength must be one of: weak, moderate, strong.',
            'style.in' => 'Style must be one of: aggressive, moderate, diplomatic.',
        ];
    }
}
