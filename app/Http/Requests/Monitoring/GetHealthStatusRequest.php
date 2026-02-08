<?php

namespace App\Http\Requests\Monitoring;

use Illuminate\Foundation\Http\FormRequest;

class GetHealthStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public health check endpoint
    }

    public function rules(): array
    {
        return [
            'service' => ['nullable', 'string', 'in:database,redis,neo4j,openai,aws,queue'],
            'detailed' => ['nullable', 'boolean'],
            'include_metrics' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'service.in' => 'Service must be one of: database, redis, neo4j, openai, aws, queue.',
        ];
    }
}
