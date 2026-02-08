<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CitationSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2|max:1000',
            'corpora' => 'sometimes|array',
            'corpora.*' => 'in:laws,decisions,cases',
            'filters' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'threshold' => 'sometimes|numeric|min:0|max:1',
            'sort_by' => 'sometimes|in:score,date',
            'sort_order' => 'sometimes|in:asc,desc',
            'deduplicate' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required',
            'query.min' => 'Search query must be at least 2 characters',
            'query.max' => 'Search query must not exceed 1000 characters',
            'corpora.*.in' => 'Invalid corpus. Must be one of: laws, decisions, cases',
            'limit.max' => 'Limit must not exceed 100',
            'per_page.max' => 'Per page must not exceed 100',
            'threshold.min' => 'Threshold must be between 0 and 1',
            'threshold.max' => 'Threshold must be between 0 and 1',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate date range if present in filters
            $dateFrom = $this->input('filters.date_from');
            $dateTo = $this->input('filters.date_to');

            if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
                $validator->errors()->add(
                    'filters.date_to',
                    'date_to must be equal to or after date_from'
                );
            }
        });
    }
}
