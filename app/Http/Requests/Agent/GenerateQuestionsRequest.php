<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class GenerateQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LegalCase::class);
    }

    public function rules(): array
    {
        return [
            'case_id' => ['required', 'string', 'exists:cases,id'],
            'context' => ['required', 'string', 'min:20', 'max:10000'],
            'question_types' => ['nullable', 'array'],
            'question_types.*' => ['string', 'in:factual,legal,procedural,strategic,evidentiary'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'focus_on' => ['nullable', 'array'],
            'focus_on.*' => ['string', 'max:255'],
            'exclude_topics' => ['nullable', 'array'],
            'exclude_topics.*' => ['string', 'max:255'],
            'include_answers' => ['nullable', 'boolean'],
            'include_citations' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'case_id.required' => 'Case ID is required for question generation.',
            'case_id.exists' => 'The specified case does not exist.',
            'context.required' => 'Context is required to generate relevant questions.',
            'context.min' => 'Context must be at least 20 characters.',
            'context.max' => 'Context cannot exceed 10,000 characters.',
            'question_types.*.in' => 'Each question type must be one of: factual, legal, procedural, strategic, evidentiary.',
            'count.min' => 'Must generate at least 1 question.',
            'count.max' => 'Cannot generate more than 20 questions at once.',
            'difficulty.in' => 'Difficulty must be one of: easy, medium, hard.',
        ];
    }
}
