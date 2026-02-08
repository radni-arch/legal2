<?php

namespace App\Http\Requests\Graph;

use Illuminate\Foundation\Http\FormRequest;

class SubgraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'node_type' => ['required', 'string', 'in:Law,Case,Decision,Keyword,Topic,Court,LegalConcept'],
            'filters' => ['nullable', 'array'],
            'max_nodes' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'include_relationships' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'node_type.required' => 'Node type is required to retrieve subgraph.',
            'node_type.in' => 'Node type must be one of: Law, Case, Decision, Keyword, Topic, Court, LegalConcept.',
            'max_nodes.max' => 'Maximum nodes cannot exceed 1000.',
        ];
    }
}
