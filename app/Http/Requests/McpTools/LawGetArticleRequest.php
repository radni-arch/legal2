<?php

namespace App\Http\Requests\McpTools;

use Illuminate\Foundation\Http\FormRequest;

class LawGetArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_id' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'integer', 'min:1'],
            'chapter' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:100'],
        ];
    }
}
