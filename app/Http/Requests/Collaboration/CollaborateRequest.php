<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Foundation\Http\FormRequest;

class CollaborateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'problem' => ['required', 'string', 'min:10', 'max:5000'],
            'problem_type' => ['nullable', 'string', 'in:employment,contract,property,family,criminal,general'],
            'context' => ['nullable', 'array'],
            'agents' => ['nullable', 'array'],
            'agents.*' => ['string', 'in:research_specialist,precedent_analyst,strategy_specialist,risk_analyst'],
            'execution_mode' => ['nullable', 'string', 'in:sequential,parallel'],
            'max_iterations' => ['nullable', 'integer', 'min:1', 'max:10'],
            'include_sources' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'problem.required' => 'Problem description is required for collaboration.',
            'problem.min' => 'Problem description must be at least 10 characters.',
            'problem_type.in' => 'Problem type must be one of: employment, contract, property, family, criminal, general.',
            'agents.*.in' => 'Each agent must be one of: research_specialist, precedent_analyst, strategy_specialist, risk_analyst.',
            'execution_mode.in' => 'Execution mode must be either sequential or parallel.',
        ];
    }
}
