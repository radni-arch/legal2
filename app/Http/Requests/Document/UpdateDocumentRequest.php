<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $this->user()->can('update', $document);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'in:evidence,pleading,motion,order,correspondence,discovery,other'],
            'author' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10', 'in:en,hr,de,fr,es'],
            'metadata' => ['nullable', 'array'],
            'content' => ['nullable', 'string', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max' => 'Title cannot exceed 500 characters.',
            'category.in' => 'Category must be one of: evidence, pleading, motion, order, correspondence, discovery, other.',
            'document_date.before_or_equal' => 'Document date cannot be in the future.',
            'language.in' => 'Language must be one of: en, hr, de, fr, es.',
            'content.max' => 'Content cannot exceed 100,000 characters.',
        ];
    }
}
