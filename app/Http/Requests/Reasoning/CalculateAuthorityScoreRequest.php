<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class CalculateAuthorityScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'decision_id' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_id.required' => 'Decision ID is required to calculate authority score.',
        ];
    }
}
