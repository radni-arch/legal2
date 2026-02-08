<?php

namespace App\Http\Requests\Api;

use App\Models\EkomPodnesak;
use Illuminate\Foundation\Http\FormRequest;

class ListPodnesciRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomPodnesak::STATUSES)),
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
