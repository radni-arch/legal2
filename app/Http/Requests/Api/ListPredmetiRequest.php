<?php

namespace App\Http\Requests\Api;

use App\Models\EkomPredmet;
use Illuminate\Foundation\Http\FormRequest;

class ListPredmetiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', array_keys(EkomPredmet::STATUSES)),
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
