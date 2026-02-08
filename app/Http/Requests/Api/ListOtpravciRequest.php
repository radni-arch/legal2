<?php

namespace App\Http\Requests\Api;

use App\Models\EkomOtpravak;
use Illuminate\Foundation\Http\FormRequest;

class ListOtpravciRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomOtpravak::STATUSES)),
            'pending' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert string 'true'/'false'/'1'/'0' to boolean for boolean validation
        if ($this->has('pending') && !is_bool($this->pending)) {
            $pending = filter_var($this->pending, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($pending !== null) {
                $this->merge(['pending' => $pending]);
            }
        }
    }
}
