<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class StartTextractJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\TextractJob::class);
    }

    public function rules(): array
    {
        return [
            'drive_file_id' => ['required_without:file', 'nullable', 'string', 'max:255'],
            'file' => ['required_without:drive_file_id', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,tiff', 'max:102400'], // 100MB max
            'case_id' => ['nullable', 'string', 'exists:cases,id'],
            'force_reprocess' => ['nullable', 'boolean'],
            'queue_name' => ['nullable', 'string', 'in:textract,high_priority,low_priority'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'batch_id' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'metadata.ocr_mode' => ['nullable', 'string', 'in:analyze,detect'],
            'metadata.language' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'drive_file_id.required_without' => 'Either a Google Drive file ID or an uploaded file is required.',
            'file.required_without' => 'Either an uploaded file or a Google Drive file ID is required.',
            'file.mimes' => 'File must be a PDF or image (JPG, PNG, TIFF).',
            'file.max' => 'File size cannot exceed 100MB.',
            'case_id.exists' => 'The specified case does not exist.',
            'queue_name.in' => 'Queue name must be one of: textract, high_priority, low_priority.',
            'priority.min' => 'Priority must be between 0 and 100.',
            'priority.max' => 'Priority must be between 0 and 100.',
            'metadata.ocr_mode.in' => 'OCR mode must be either analyze or detect.',
        ];
    }
}
