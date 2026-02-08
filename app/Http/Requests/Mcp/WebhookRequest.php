<?php

namespace App\Http\Requests\Mcp;

use App\Http\Requests\Concerns\HasCommonValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
{
    use HasCommonValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'function_name' => ['required_without:name', 'nullable', 'string', 'max:255'],
            'name' => ['required_without:function_name', 'nullable', 'string', 'max:255'],
            'arguments' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'function_name.required_without' => 'Either function_name or name is required.',
            'name.required_without' => 'Either function_name or name is required.',
            'function_name.max' => 'Function name cannot exceed 255 characters.',
            'name.max' => 'Function name cannot exceed 255 characters.',
            'arguments.array' => 'Arguments must be an array.',
        ];
    }

    /**
     * Prepare data for validation by normalizing function_name/name.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && ! $this->has('function_name')) {
            $this->merge(['function_name' => $this->input('name')]);
        }
    }
}
