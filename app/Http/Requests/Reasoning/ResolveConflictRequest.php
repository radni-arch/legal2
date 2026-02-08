<?php

namespace App\Http\Requests\Reasoning;

use Illuminate\Foundation\Http\FormRequest;

class ResolveConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'laws' => ['required', 'array', 'min:2'],
            'laws.*.id' => ['required', 'string', 'max:255'],
            'laws.*.title' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'laws.required' => 'At least two laws are required to resolve conflicts.',
            'laws.min' => 'Please provide at least 2 laws to compare.',
            'laws.*.id.required' => 'Each law must have an ID.',
            'laws.*.title.required' => 'Each law must have a title.',
        ];
    }
}
