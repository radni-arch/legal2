<?php

namespace App\Http\Requests\Collaboration;

use App\Http\Requests\Concerns\HasCommonValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class RecentCollaborationsRequest extends FormRequest
{
    use HasCommonValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'limit.integer' => 'Limit must be an integer.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 100.',
        ];
    }
}
