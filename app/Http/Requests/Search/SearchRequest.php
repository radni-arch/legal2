<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'type' => ['nullable', 'string', 'in:full_text,semantic,hybrid'],
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', 'in:laws,decisions,cases,textract'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:relevance,date,title'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required.',
            'query.min' => 'Search query must be at least 2 characters.',
            'query.max' => 'Search query cannot exceed 500 characters.',
            'type.in' => 'Search type must be one of: full_text, semantic, hybrid.',
            'sources.*.in' => 'Each source must be one of: laws, decisions, cases, textract.',
            'per_page.max' => 'Results per page cannot exceed 100.',
        ];
    }
}
