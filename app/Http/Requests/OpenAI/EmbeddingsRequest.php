<?php

namespace App\Http\Requests\OpenAI;

use Illuminate\Foundation\Http\FormRequest;

class EmbeddingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // API token middleware handles auth
    }

    public function rules(): array
    {
        return [
            'input' => ['required'],
            'model' => ['nullable', 'string', 'in:text-embedding-3-small,text-embedding-3-large,text-embedding-ada-002'],
            'encoding_format' => ['nullable', 'string', 'in:float,base64'],
            'dimensions' => ['nullable', 'integer', 'in:256,512,1024,1536,3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'input.required' => 'Input text is required for embeddings.',
            'model.in' => 'Model must be one of: text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002.',
            'encoding_format.in' => 'Encoding format must be either float or base64.',
            'dimensions.in' => 'Dimensions must be one of: 256, 512, 1024, 1536, 3072.',
        ];
    }
}
