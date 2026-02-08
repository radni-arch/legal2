<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'law_id' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'law_id.required' => 'Law ID is required to analyze conflicts.',
        ];
    }
}
