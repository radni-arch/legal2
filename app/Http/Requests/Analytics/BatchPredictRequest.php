<?php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class BatchPredictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_ids' => ['required', 'array', 'min:1', 'max:50'],
            'case_ids.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_ids.required' => 'At least one case ID is required for batch prediction.',
            'case_ids.min' => 'At least one case ID is required.',
            'case_ids.max' => 'Cannot process more than 50 cases at once.',
        ];
    }
}
