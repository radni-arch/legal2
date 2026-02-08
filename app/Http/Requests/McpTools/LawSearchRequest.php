<?php

namespace App\Http\Requests\McpTools;

use Illuminate\Foundation\Http\FormRequest;

class LawSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:500'],
            'doc_id' => ['nullable', 'string', 'max:255'],
            'law_number' => ['nullable', 'string', 'max:100'],
            'jurisdiction' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }
}
