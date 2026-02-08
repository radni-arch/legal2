<?php

namespace App\Http\Requests\Ingest;

use Illuminate\Foundation\Http\FormRequest;

class SearchVectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'agent' => ['required', 'string', 'max:100'],
            'namespace' => ['nullable', 'string', 'max:100'],
            'query' => ['required', 'string', 'min:2', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
