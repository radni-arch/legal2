<?php

namespace App\Http\Requests\Case;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_number' => ['nullable', 'string', 'max:100', 'unique:cases,case_number'],
            'title' => ['required', 'string', 'max:500'],
            'client_name' => ['required', 'string', 'max:255'],
            'opponent_name' => ['nullable', 'string', 'max:255'],
            'court' => ['nullable', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'judge' => ['nullable', 'string', 'max:255'],
            'filing_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', 'string', 'in:active,pending,closed,archived'],
            'description' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_number.unique' => 'A case with this case number already exists.',
            'title.required' => 'Case title is required.',
            'title.max' => 'Case title cannot exceed 500 characters.',
            'client_name.required' => 'Client name is required.',
            'client_name.max' => 'Client name cannot exceed 255 characters.',
            'filing_date.before_or_equal' => 'Filing date cannot be in the future.',
            'status.required' => 'Case status is required.',
            'status.in' => 'Status must be one of: active, pending, closed, archived.',
            'description.max' => 'Description cannot exceed 10,000 characters.',
        ];
    }
}
