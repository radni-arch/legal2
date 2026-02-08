<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class ImproveAccusedStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'focus_areas' => ['nullable', 'array'],
            'focus_areas.*' => ['string', 'max:255'],
            'include_mitigating' => ['nullable', 'boolean'],
            'include_weaknesses' => ['nullable', 'boolean'],
            'include_procedural' => ['nullable', 'boolean'],
            'include_constitutional' => ['nullable', 'boolean'],
            'depth' => ['nullable', 'string', 'in:shallow,medium,deep'],
        ];
    }

    public function messages(): array
    {
        return [
            'focus_areas.*.max' => 'Each focus area cannot exceed 255 characters.',
            'depth.in' => 'Analysis depth must be one of: shallow, medium, deep.',
        ];
    }
}
