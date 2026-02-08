<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDeductiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'facts' => ['required', 'array', 'min:1'],
            'facts.*' => ['string'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'facts.required' => 'Facts array is required for deductive reasoning.',
            'facts.min' => 'At least one fact is required.',
            'rules.required' => 'Rules array is required for deductive reasoning.',
            'rules.min' => 'At least one rule is required.',
        ];
    }
}
