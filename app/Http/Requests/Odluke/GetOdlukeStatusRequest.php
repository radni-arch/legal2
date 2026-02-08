<?php

namespace App\Http\Requests\Odluke;

use Illuminate\Foundation\Http\FormRequest;

class GetOdlukeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'cache_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cache_key.required' => 'Cache key is required to check Odluke agent status.',
        ];
    }
}
