<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;

class DirectUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'], // 100MB
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File is required for upload.',
            'file.max' => 'File size cannot exceed 100MB.',
        ];
    }
}
