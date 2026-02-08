<?php

namespace App\Http\Requests\McpTools;

use Illuminate\Foundation\Http\FormRequest;

class DecisionGetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255'],
            'include_documents' => ['nullable', 'boolean'],
            'include_content' => ['nullable', 'boolean'],
        ];
    }
}
