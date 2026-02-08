<?php

namespace App\Http\Requests\Graph;

use Illuminate\Foundation\Http\FormRequest;

class GetSubgraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string', 'max:255'],
            'node_type' => ['nullable', 'string', 'in:Law,Case,Decision,Keyword,Topic,Court,LegalConcept'],
            'depth' => ['nullable', 'integer', 'min:1', 'max:5'],
            'relationship_types' => ['nullable', 'array'],
            'relationship_types.*' => ['string', 'in:CITES,REFERENCES,RELATES_TO,HAS_KEYWORD,SIMILAR_TO'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'node_id.required' => 'Node ID is required to retrieve subgraph.',
            'node_type.in' => 'Node type must be one of: Law, Case, Decision, Keyword, Topic, Court, LegalConcept.',
            'depth.min' => 'Depth must be at least 1.',
            'depth.max' => 'Depth cannot exceed 5 levels.',
            'relationship_types.*.in' => 'Each relationship type must be one of: CITES, REFERENCES, RELATES_TO, HAS_KEYWORD, SIMILAR_TO.',
            'limit.max' => 'Result limit cannot exceed 500 nodes.',
        ];
    }
}
