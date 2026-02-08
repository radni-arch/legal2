<?php

namespace App\Http\Requests\Concerns;

/**
 * Common validation rule presets for FormRequests
 *
 * This trait provides reusable validation rule arrays for common patterns
 * across the application, ensuring consistency and reducing duplication.
 */
trait HasCommonValidationRules
{
    /**
     * Standard case ID validation rules
     */
    protected function caseIdRules(): array
    {
        return ['required', 'string', 'exists:cases,id'];
    }

    /**
     * Optional case ID validation rules
     */
    protected function optionalCaseIdRules(): array
    {
        return ['nullable', 'string', 'exists:cases,id'];
    }

    /**
     * Standard date range validation rules
     */
    protected function dateRangeRules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Standard pagination rules
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Standard string length rules for short text (max 255)
     */
    protected function shortTextRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Standard string length rules for medium text (max 1000)
     */
    protected function mediumTextRules(): array
    {
        return ['required', 'string', 'max:1000'];
    }

    /**
     * Standard string length rules for long text (max 10000)
     */
    protected function longTextRules(): array
    {
        return ['required', 'string', 'max:10000'];
    }

    /**
     * Standard threshold validation (0-100)
     */
    protected function thresholdRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:100'];
    }

    /**
     * Standard boolean flag rules
     */
    protected function booleanFlagRules(): array
    {
        return ['nullable', 'boolean'];
    }

    /**
     * Standard array of strings rules
     */
    protected function stringArrayRules(): array
    {
        return [
            'nullable',
            'array',
        ];
    }

    /**
     * Standard topic validation rules
     */
    protected function topicRules(): array
    {
        return [
            'required',
            'string',
            'in:drug_charge_severity,home_search_abuse,bail_denial,pretrial_detention,witness_intimidation',
        ];
    }

    /**
     * Standard OpenAI model selection rules
     */
    protected function openAIModelRules(): array
    {
        return [
            'nullable',
            'string',
            'in:gpt-4o,gpt-4o-mini,gpt-4-turbo,gpt-3.5-turbo',
        ];
    }

    /**
     * Standard file upload rules (documents)
     */
    protected function documentFileRules(): array
    {
        return [
            'required',
            'file',
            'mimes:pdf,doc,docx,txt',
            'max:51200', // 50MB
        ];
    }

    /**
     * Standard file upload rules (images)
     */
    protected function imageFileRules(): array
    {
        return [
            'required',
            'file',
            'mimes:jpg,jpeg,png,gif',
            'max:10240', // 10MB
        ];
    }

    /**
     * Standard language code rules
     */
    protected function languageCodeRules(): array
    {
        return [
            'nullable',
            'string',
            'in:en,hr,de,fr,es',
        ];
    }

    /**
     * Standard status enum rules
     */
    protected function statusRules(): array
    {
        return [
            'required',
            'string',
            'in:active,pending,closed,archived',
        ];
    }

    /**
     * Standard priority rules (0-100)
     */
    protected function priorityRules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:100'];
    }

    /**
     * Standard metadata array rules
     */
    protected function metadataRules(): array
    {
        return ['nullable', 'array'];
    }

    /**
     * Common custom error messages
     */
    protected function commonMessages(): array
    {
        return [
            'case_id.required' => 'Case ID is required.',
            'case_id.exists' => 'The specified case does not exist.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'per_page.max' => 'Results per page cannot exceed 100.',
        ];
    }
}
