<?php

namespace App\Http\Requests\Ingest;

use Illuminate\Foundation\Http\FormRequest;

class IngestLawsCroatiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'since_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'max_acts' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'agent' => ['nullable', 'string', 'max:100'],
            'namespace' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'chunk_chars' => ['nullable', 'integer', 'min:200', 'max:10000'],
            'overlap' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
