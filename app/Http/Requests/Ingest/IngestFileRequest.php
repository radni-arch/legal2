<?php

namespace App\Http\Requests\Ingest;

use Illuminate\Foundation\Http\FormRequest;

class IngestFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:51200'], // 50MB
            'chunk_chars' => ['nullable', 'integer', 'min:200', 'max:10000'],
            'overlap' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
