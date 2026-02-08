<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateLogicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'argument' => ['required', 'string', 'min:20', 'max:10000'],
            'evaluation_criteria' => ['nullable', 'array'],
            'evaluation_criteria.*' => ['string', 'in:validity,soundness,coherence,relevance,completeness'],
            'identify_fallacies' => ['nullable', 'boolean'],
            'suggest_fixes' => ['nullable', 'boolean'],
            'include_examples' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'argument.required' => 'Argument is required for logic evaluation.',
            'argument.min' => 'Argument must be at least 20 characters.',
            'argument.max' => 'Argument cannot exceed 10,000 characters.',
            'evaluation_criteria.*.in' => 'Each criterion must be one of: validity, soundness, coherence, relevance, completeness.',
        ];
    }
}
