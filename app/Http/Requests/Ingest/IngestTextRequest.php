<?php

namespace App\Http\Requests\Ingest;

use Illuminate\Foundation\Http\FormRequest;

class IngestTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'agent' => ['required', 'string', 'max:100'],
            'namespace' => ['nullable', 'string', 'max:100'],
            'text' => ['required', 'string', 'min:10', 'max:1000000'],
            'chunk_chars' => ['nullable', 'integer', 'min:200', 'max:10000'],
            'overlap' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'agent.required' => 'Agent name is required for text ingestion.',
            'text.required' => 'Text content is required.',
            'chunk_chars.min' => 'Chunk size must be at least 200 characters.',
        ];
    }
}
