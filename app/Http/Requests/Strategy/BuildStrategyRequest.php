<?php

namespace App\Http\Requests\Strategy;

use Illuminate\Foundation\Http\FormRequest;

class BuildStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'objectives' => ['required', 'array', 'min:1'],
            'objectives.*' => ['required', 'string', 'max:500'],
            'priority_level' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'constraints' => ['nullable', 'array'],
            'constraints.budget' => ['nullable', 'numeric', 'min:0'],
            'constraints.resources' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'objectives.required' => 'At least one objective is required to build a strategy.',
            'objectives.min' => 'Please provide at least one strategic objective.',
            'objectives.*.required' => 'Each objective must have a description.',
            'objectives.*.max' => 'Each objective cannot exceed 500 characters.',
            'priority_level.in' => 'Priority level must be one of: low, medium, high, critical.',
            'deadline.after' => 'Deadline must be a future date.',
        ];
    }
}
