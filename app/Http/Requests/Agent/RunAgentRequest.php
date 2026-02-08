<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RunAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'agent_type' => ['required', 'string', 'in:research,decision_discovery,odluke,question_generator'],
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'parameters' => ['nullable', 'array'],
            'parameters.topic' => ['nullable', 'string', 'max:500'],
            'parameters.focus_areas' => ['nullable', 'array'],
            'parameters.focus_areas.*' => ['string', 'max:255'],
            'max_iterations' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_time_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'cost_budget_usd' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'async' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_type.required' => 'Agent type is required.',
            'agent_type.in' => 'Agent type must be one of: research, decision_discovery, odluke, question_generator.',
            'case_id.required' => 'Case ID is required for agent execution.',
            'case_id.exists' => 'The specified case does not exist.',
            'max_iterations.max' => 'Maximum iterations cannot exceed 10.',
            'max_time_seconds.max' => 'Maximum execution time cannot exceed 1 hour (3600 seconds).',
            'cost_budget_usd.max' => 'Cost budget cannot exceed $10 USD.',
        ];
    }
}
