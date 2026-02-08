<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\CaseDocument::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,txt,jpg,jpeg,png', 'max:51200'], // 50MB max
            'title' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'in:evidence,pleading,motion,order,correspondence,discovery,other'],
            'author' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10', 'in:en,hr,de,fr,es'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required to upload a document.',
            'case_id.exists' => 'The specified case does not exist.',
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'File must be a PDF, Word document, text file, or image (JPG, PNG).',
            'file.max' => 'File size cannot exceed 50MB.',
            'category.in' => 'Category must be one of: evidence, pleading, motion, order, correspondence, discovery, other.',
            'document_date.before_or_equal' => 'Document date cannot be in the future.',
            'language.in' => 'Language must be one of: en, hr, de, fr, es.',
        ];
    }
}
