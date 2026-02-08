<?php

namespace Tests\TestData\Validators;

class LegalDataValidator
{
    private const VALID_LAWS = ['ZKP', 'KZ', 'Ustav RH'];

    private const VALID_CASE_TYPES = ['criminal', 'civil', 'commercial', 'labor'];

    private const COURT_HIERARCHY = [
        'supreme' => 4,
        'high' => 3,
        'county' => 2,
        'municipal' => 1,
    ];

    /**
     * Validate Croatian legal citation format.
     * Expected format: "LAW Čl. NUMBER" (e.g., "ZKP Čl. 12")
     */
    public function validateCitation(string $citation): ValidationResult
    {
        // Format: "LAW Čl. NUMBER" where LAW is one of: ZKP, KZ, Ustav RH
        if (! preg_match('/^(ZKP|KZ|Ustav RH) Čl\. \d+$/', $citation)) {
            return ValidationResult::failure(['Invalid citation format. Expected format: "ZKP Čl. 12"']);
        }

        return ValidationResult::success();
    }

    /**
     * Validate Croatian court hierarchy.
     * Courts must be properly ordered: municipal < county < high < supreme
     */
    public function validateCourtHierarchy(string $court, ?string $parentCourt = null): ValidationResult
    {
        if (! isset(self::COURT_HIERARCHY[$court])) {
            return ValidationResult::failure(["Unknown court level: {$court}"]);
        }

        if ($parentCourt !== null) {
            if (! isset(self::COURT_HIERARCHY[$parentCourt])) {
                return ValidationResult::failure(["Unknown parent court level: {$parentCourt}"]);
            }

            // Parent must be higher in hierarchy (higher numeric value)
            if (self::COURT_HIERARCHY[$court] >= self::COURT_HIERARCHY[$parentCourt]) {
                return ValidationResult::failure([
                    "Invalid court hierarchy: {$court} cannot be under {$parentCourt}",
                ]);
            }
        }

        return ValidationResult::success();
    }

    /**
     * Validate case type.
     * Valid types: criminal, civil, commercial, labor
     */
    public function validateCaseType(string $type): ValidationResult
    {
        if (! in_array($type, self::VALID_CASE_TYPES)) {
            return ValidationResult::failure([
                "Invalid case type: {$type}. Must be one of: ".implode(', ', self::VALID_CASE_TYPES),
            ]);
        }

        return ValidationResult::success();
    }

    /**
     * Run all validations on a data structure.
     * Expects data array with keys: citation, court, parent_court (optional), case_type
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        // Validate citation if present
        if (isset($data['citation'])) {
            $result = $this->validateCitation($data['citation']);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        // Validate court hierarchy if present
        if (isset($data['court'])) {
            $parentCourt = $data['parent_court'] ?? null;
            $result = $this->validateCourtHierarchy($data['court'], $parentCourt);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        // Validate case type if present
        if (isset($data['case_type'])) {
            $result = $this->validateCaseType($data['case_type']);
            if (! $result->isValid()) {
                $errors = array_merge($errors, $result->errors());
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
    }
}
