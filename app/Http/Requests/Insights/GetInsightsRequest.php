<?php

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

class GetInsightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'objective' => ['nullable', 'string', 'min:3', 'max:500'],
            'namespace' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'objective.min' => 'Objective must be at least 3 characters.',
            'limit.max' => 'Limit cannot exceed 100.',
            'days.max' => 'Days cannot exceed 365.',
        ];
    }
}
