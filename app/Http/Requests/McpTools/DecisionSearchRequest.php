<?php

namespace App\Http\Requests\McpTools;

use Illuminate\Foundation\Http\FormRequest;

class DecisionSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:500'],
            'case_number' => ['nullable', 'string', 'max:100'],
            'court' => ['nullable', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:100'],
            'judge' => ['nullable', 'string', 'max:255'],
        ];
    }
}
