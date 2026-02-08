<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnifiedSearchRequest extends FormRequest
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
            'weights' => 'sometimes|array',
            'weights.laws' => 'sometimes|numeric|min:0|max:10',
            'weights.decisions' => 'sometimes|numeric|min:0|max:10',
            'weights.cases' => 'sometimes|numeric|min:0|max:10',
            'filters' => 'sometimes|array',
            'filters.jurisdiction' => 'sometimes|string',
            'filters.country' => 'sometimes|string',
            'filters.court' => 'sometimes|string',
            'filters.decision_type' => 'sometimes|string',
            'filters.category' => 'sometimes|string',
            'filters.language' => 'sometimes|string',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'threshold' => 'sometimes|numeric|min:0|max:1',
            'model' => 'sometimes|string',
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
            'filters.date_from.date' => 'Invalid date_from format. Use YYYY-MM-DD',
            'filters.date_to.date' => 'Invalid date_to format. Use YYYY-MM-DD',
            'filters.date_to.after_or_equal' => 'date_to must be equal to or after date_from',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate date range
            $dateFrom = $this->input('filters.date_from');
            $dateTo = $this->input('filters.date_to');

            if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
                $validator->errors()->add(
                    'filters.date_to',
                    'date_to must be equal to or after date_from'
                );
            }

            // Validate that weights correspond to selected corpora
            $corpora = $this->input('corpora', ['laws', 'decisions', 'cases']);
            $weights = $this->input('weights', []);

            if (! empty($weights)) {
                foreach (array_keys($weights) as $corpus) {
                    if (! in_array($corpus, $corpora)) {
                        $validator->errors()->add(
                            'weights',
                            "Weight specified for '{$corpus}' but it's not in the selected corpora"
                        );
                    }
                }
            }
        });
    }
}
