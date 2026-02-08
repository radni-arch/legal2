<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class ParseLogicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'law_text' => ['required', 'string', 'min:10', 'max:50000'],
        ];
    }

    public function messages(): array
    {
        return [
            'law_text.required' => 'Law text is required to parse logical structure.',
            'law_text.min' => 'Law text must be at least 10 characters.',
            'law_text.max' => 'Law text cannot exceed 50,000 characters.',
        ];
    }
}
