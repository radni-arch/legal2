<?php

namespace App\Http\Requests\Topic;

use Illuminate\Foundation\Http\FormRequest;

class GetTopicStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'in:drug_charge_severity,home_search_abuse,bail_denial,pretrial_detention,witness_intimidation'],
            'region' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'court' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic.required' => 'Topic is required for statistics.',
            'topic.in' => 'Topic must be one of: drug_charge_severity, home_search_abuse, bail_denial, pretrial_detention, witness_intimidation.',
            'year.min' => 'Year must be 2000 or later.',
            'year.max' => 'Year must be 2100 or earlier.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
