<?php

namespace App\Http\Requests\Strategy;

use Illuminate\Foundation\Http\FormRequest;

class CreateActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'objectives' => ['nullable', 'array'],
            'objectives.*' => ['string', 'max:500'],
            'timeline_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'include_milestones' => ['nullable', 'boolean'],
            'include_resources' => ['nullable', 'boolean'],
            'risk_assessment' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'objectives.*.max' => 'Each objective cannot exceed 500 characters.',
            'timeline_weeks.min' => 'Timeline must be at least 1 week.',
            'timeline_weeks.max' => 'Timeline cannot exceed 52 weeks (1 year).',
        ];
    }
}
