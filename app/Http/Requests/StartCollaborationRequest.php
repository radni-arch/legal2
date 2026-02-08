<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartCollaborationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by api.token middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pipeline' => ['required', 'array', 'min:1'],
            'pipeline.*' => ['required', 'string'],
            'task_description' => ['required', 'string', 'min:3'],
            'initial_context' => ['sometimes', 'array'],
            'budgets' => ['sometimes', 'array'],
            'budgets.token_budget' => ['sometimes', 'integer', 'min:1'],
            'budgets.cost_budget' => ['sometimes', 'numeric', 'min:0'],
            'budgets.time_budget_ms' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'pipeline.required' => 'Agent pipeline is required',
            'pipeline.array' => 'Pipeline must be an array of agent names',
            'pipeline.min' => 'Pipeline must contain at least one agent',
            'task_description.required' => 'Task description is required',
            'task_description.min' => 'Task description must be at least 3 characters',
            'budgets.token_budget.integer' => 'Token budget must be an integer',
            'budgets.cost_budget.numeric' => 'Cost budget must be a number',
            'budgets.time_budget_ms.integer' => 'Time budget must be an integer (milliseconds)',
        ];
    }
}
