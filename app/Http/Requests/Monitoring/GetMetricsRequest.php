<?php

namespace App\Http\Requests\Monitoring;

use Illuminate\Foundation\Http\FormRequest;

class GetMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\LegalCase::class) ?? true;
    }

    public function rules(): array
    {
        return [
            'metric_type' => ['nullable', 'string', 'in:api_usage,token_consumption,cost,performance,errors'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'granularity' => ['nullable', 'string', 'in:hour,day,week,month'],
            'group_by' => ['nullable', 'string', 'in:model,endpoint,user,case'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'metric_type.in' => 'Metric type must be one of: api_usage, token_consumption, cost, performance, errors.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'granularity.in' => 'Granularity must be one of: hour, day, week, month.',
            'group_by.in' => 'Group by must be one of: model, endpoint, user, case.',
            'limit.max' => 'Limit cannot exceed 1000 entries.',
        ];
    }
}
