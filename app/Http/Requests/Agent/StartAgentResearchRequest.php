<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StartAgentResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', 'min:10', 'max:1000'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:100'],
            'max_iterations' => ['nullable', 'integer', 'min:1', 'max:50'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'token_budget' => ['nullable', 'numeric', 'min:0'],
            'cost_budget' => ['nullable', 'numeric', 'min:0'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:10', 'max:7200'],
            'async' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'objective.required' => 'Research objective is required.',
            'objective.min' => 'Objective must be at least 10 characters.',
            'max_iterations.max' => 'Maximum iterations cannot exceed 50.',
            'threshold.min' => 'Threshold must be between 0 and 1.',
            'threshold.max' => 'Threshold must be between 0 and 1.',
            'time_limit_seconds.max' => 'Time limit cannot exceed 2 hours (7200 seconds).',
        ];
    }
}
