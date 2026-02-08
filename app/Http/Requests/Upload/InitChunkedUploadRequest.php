<?php

namespace App\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;

class InitChunkedUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'totalSize' => ['required', 'integer', 'min:1'],
            'chunkSize' => ['required', 'integer', 'min:1', 'max:10485760'], // 10MB
            'mime' => ['nullable', 'string', 'max:100'],
        ];
    }
}
