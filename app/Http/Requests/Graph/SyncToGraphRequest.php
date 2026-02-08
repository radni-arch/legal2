<?php

namespace App\Http\Requests\Graph;

use Illuminate\Foundation\Http\FormRequest;

class SyncToGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:law,decision,case,textract_job'],
            'entity_id' => ['required', 'string', 'max:255'],
            'force_resync' => ['nullable', 'boolean'],
            'sync_relationships' => ['nullable', 'boolean'],
            'extract_keywords' => ['nullable', 'boolean'],
            'extract_citations' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'entity_type.required' => 'Entity type is required for graph sync.',
            'entity_type.in' => 'Entity type must be one of: law, decision, case, textract_job.',
            'entity_id.required' => 'Entity ID is required for graph sync.',
        ];
    }
}
