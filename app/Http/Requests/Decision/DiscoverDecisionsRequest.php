<?php

namespace App\Http\Requests\Decision;

use Illuminate\Foundation\Http\FormRequest;

class DiscoverDecisionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'keywords' => ['required', 'string', 'min:2', 'max:500'],
            'court' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'decision_type' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'keywords.required' => 'Keywords are required to discover decisions.',
            'keywords.min' => 'Keywords must be at least 2 characters.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'limit.max' => 'Limit cannot exceed 100 decisions.',
        ];
    }
}
