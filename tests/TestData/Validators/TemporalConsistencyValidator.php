<?php

namespace Tests\TestData\Validators;

class TemporalConsistencyValidator
{
    /**
     * Validate that events are in chronological order.
     * Events should have 'name' and 'date' keys.
     */
    public function validateTimeline(array $events): ValidationResult
    {
        $errors = [];

        for ($i = 1; $i < count($events); $i++) {
            $prev = $events[$i - 1];
            $curr = $events[$i];

            if ($curr['date']->lt($prev['date'])) {
                $errors[] = "Event '{$curr['name']}' occurs before '{$prev['name']}' but should be chronological";
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    /**
     * Validate case dates are in proper order.
     * Expected order: filing_date < hearing_dates < decision_date
     */
    public function validateCaseDates(object $case): ValidationResult
    {
        $errors = [];

        // Validate filing_date vs decision_date
        if (isset($case->filing_date, $case->decision_date)) {
            if ($case->decision_date->lt($case->filing_date)) {
                $errors[] = 'Decision date cannot be before filing date';
            }
        }

        // Validate hearing_dates
        if (isset($case->hearing_dates) && is_array($case->hearing_dates)) {
            foreach ($case->hearing_dates as $hearingDate) {
                // Hearings must be after filing
                if (isset($case->filing_date) && $hearingDate->lt($case->filing_date)) {
                    $errors[] = 'Hearing date cannot be before filing date';
                }

                // Hearings must be before decision
                if (isset($case->decision_date) && $hearingDate->gt($case->decision_date)) {
                    $errors[] = 'Hearing date cannot be after decision date';
                }
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    /**
     * Validate evidence collection dates fall within case timeline.
     * Evidence must be collected after filing and before decision.
     */
    public function validateEvidenceDates(object $evidence, object $case): ValidationResult
    {
        $errors = [];

        if (isset($evidence->collection_date)) {
            // Evidence cannot be collected before case filing
            if (isset($case->filing_date) && $evidence->collection_date->lt($case->filing_date)) {
                $errors[] = 'Evidence collection date is before case filing date';
            }

            // Evidence cannot be collected after case decision (if decision exists)
            if (isset($case->decision_date) && $evidence->collection_date->gt($case->decision_date)) {
                $errors[] = 'Evidence collection date is after case decision date';
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }

    /**
     * Run all temporal validations on a data structure.
     * Expects data array with keys: case, events, evidence (optional)
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Validate timeline if present
        if (isset($data['events'])) {
            $result = $this->validateTimeline($data['events']);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        // Validate case dates if present
        if (isset($data['case'])) {
            $result = $this->validateCaseDates($data['case']);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        // Validate evidence dates if both evidence and case are present
        if (isset($data['evidence'], $data['case'])) {
            $result = $this->validateEvidenceDates($data['evidence'], $data['case']);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }
}
