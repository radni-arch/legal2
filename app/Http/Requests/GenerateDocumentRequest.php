<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by middleware (auth:sanctum)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $documentTypes = array_keys(config('documents.types', []));

        return [
            'document_type' => ['required', 'string', 'in:'.implode(',', $documentTypes)],
            'context' => 'nullable|string|max:10000',
            'case_id' => 'nullable|exists:cases,id',
            'evidence_ids' => 'nullable|array',
            'evidence_ids.*' => 'exists:evidence,id',
            'decision_ids' => 'nullable|array',
            'decision_ids.*' => 'exists:court_decision_documents,id',
            'law_ids' => 'nullable|array',
            'law_ids.*' => 'exists:laws,id',
            'additional_context' => 'nullable|string|max:5000',
            'confirm_escalation' => 'nullable|boolean',
            'model_config' => 'nullable|array',
            'model_config.model' => 'nullable|string',
            'model_config.temperature' => 'nullable|numeric|min:0|max:2',
            'model_config.max_tokens' => 'nullable|integer|min:1|max:16000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_type.required' => 'Document type is required',
            'document_type.in' => 'Invalid document type. Must be one of the configured document types.',
            'case_id.exists' => 'The specified case does not exist',
            'evidence_ids.*.exists' => 'One or more evidence IDs are invalid',
            'decision_ids.*.exists' => 'One or more decision IDs are invalid',
            'law_ids.*.exists' => 'One or more law IDs are invalid',
            'context.max' => 'Context must not exceed 10,000 characters',
            'additional_context.max' => 'Additional context must not exceed 5,000 characters',
        ];
    }
}
