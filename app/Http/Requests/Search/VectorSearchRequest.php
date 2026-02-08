<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class VectorSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:3', 'max:1000'],
            'corpus' => ['required', 'string', 'in:laws,decisions,cases,textract'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'filters' => ['nullable', 'array'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date', 'after_or_equal:filters.date_from'],
            'filters.court' => ['nullable', 'string', 'max:255'],
            'filters.case_type' => ['nullable', 'string', 'max:100'],
            'include_metadata' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Vector search query is required.',
            'query.min' => 'Query must be at least 3 characters.',
            'query.max' => 'Query cannot exceed 1000 characters.',
            'corpus.required' => 'Corpus selection is required.',
            'corpus.in' => 'Corpus must be one of: laws, decisions, cases, textract.',
            'limit.max' => 'Result limit cannot exceed 100.',
            'threshold.min' => 'Similarity threshold must be between 0 and 1.',
            'threshold.max' => 'Similarity threshold must be between 0 and 1.',
            'filters.date_to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
