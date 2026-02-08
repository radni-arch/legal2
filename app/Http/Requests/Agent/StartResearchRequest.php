<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StartResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'research_topic' => ['required', 'string', 'min:10', 'max:1000'],
            'focus_areas' => ['nullable', 'array'],
            'focus_areas.*' => ['string', 'max:255'],
            'depth' => ['nullable', 'string', 'in:shallow,medium,deep'],
            'max_time_minutes' => ['nullable', 'integer', 'min:5', 'max:120'],
            'include_decisions' => ['nullable', 'boolean'],
            'include_laws' => ['nullable', 'boolean'],
            'include_precedents' => ['nullable', 'boolean'],
            'jurisdictions' => ['nullable', 'array'],
            'jurisdictions.*' => ['string', 'max:100'],
            'date_range' => ['nullable', 'array'],
            'date_range.from' => ['nullable', 'date'],
            'date_range.to' => ['nullable', 'date', 'after_or_equal:date_range.from'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required to start research.',
            'case_id.exists' => 'The specified case does not exist.',
            'research_topic.required' => 'Research topic is required.',
            'research_topic.min' => 'Research topic must be at least 10 characters.',
            'research_topic.max' => 'Research topic cannot exceed 1000 characters.',
            'depth.in' => 'Research depth must be one of: shallow, medium, deep.',
            'max_time_minutes.max' => 'Maximum research time cannot exceed 2 hours (120 minutes).',
            'date_range.to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
