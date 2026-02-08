<?php

namespace App\Http\Requests\McpTools;

use Illuminate\Foundation\Http\FormRequest;

class CaseSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['nullable', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:100'],
            'query' => ['nullable', 'string', 'max:500'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'opponent_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
