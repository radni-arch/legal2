# Validator Usage Examples

This document demonstrates how to use the test data validators for Croatian legal test data.

## LegalDataValidator

Validates legal accuracy of test data including citations, court hierarchy, and case types.

### Example 1: Validate Citation Format

```php
use Tests\TestData\Validators\LegalDataValidator;

$validator = new LegalDataValidator();

// Valid Croatian legal citations
$result = $validator->validateCitation('ZKP Čl. 12');
if ($result->isValid()) {
    echo "Citation is valid";
}

$result = $validator->validateCitation('KZ Čl. 87');
$result = $validator->validateCitation('Ustav RH Čl. 29');

// Invalid citation - will fail
$result = $validator->validateCitation('Invalid Format');
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Invalid citation format. Expected format: "ZKP Čl. 12"']
}
```

### Example 2: Validate Court Hierarchy

```php
use Tests\TestData\Validators\LegalDataValidator;

$validator = new LegalDataValidator();

// Valid hierarchy: municipal < county < high < supreme
$result = $validator->validateCourtHierarchy('county', 'high');
// Valid: county court under high court

$result = $validator->validateCourtHierarchy('municipal', 'county');
// Valid: municipal court under county court

$result = $validator->validateCourtHierarchy('supreme');
// Valid: top-level court without parent

// Invalid hierarchy
$result = $validator->validateCourtHierarchy('supreme', 'county');
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Invalid court hierarchy: supreme cannot be under county']
}
```

### Example 3: Validate Case Type

```php
use Tests\TestData\Validators\LegalDataValidator;

$validator = new LegalDataValidator();

// Valid case types: criminal, civil, commercial, labor
$result = $validator->validateCaseType('criminal');
$result = $validator->validateCaseType('civil');

// Invalid case type
$result = $validator->validateCaseType('traffic');
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Invalid case type: traffic. Must be one of: criminal, civil, commercial, labor']
}
```

### Example 4: Validate Complete Test Data

```php
use Tests\TestData\Validators\LegalDataValidator;

$validator = new LegalDataValidator();

$testData = [
    'citation' => 'ZKP Čl. 12',
    'court' => 'county',
    'parent_court' => 'high',
    'case_type' => 'criminal'
];

$result = $validator->validate($testData);
if ($result->isValid()) {
    echo "All legal validations passed!";
}

// With errors
$invalidData = [
    'citation' => 'Invalid',
    'court' => 'supreme',
    'parent_court' => 'county',
    'case_type' => 'invalid'
];

$result = $validator->validate($invalidData);
if (!$result->isValid()) {
    echo "Validation errors found:\n";
    foreach ($result->errors() as $error) {
        echo "- $error\n";
    }
    // Output:
    // - Invalid citation format. Expected format: "ZKP Čl. 12"
    // - Invalid court hierarchy: supreme cannot be under county
    // - Invalid case type: invalid. Must be one of: criminal, civil, commercial, labor
}
```

## TemporalConsistencyValidator

Validates timeline coherence and date relationships in test data.

### Example 1: Validate Event Timeline

```php
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

$validator = new TemporalConsistencyValidator();

// Valid chronological timeline
$events = [
    ['name' => 'Filing', 'date' => Carbon::parse('2024-01-01')],
    ['name' => 'Hearing', 'date' => Carbon::parse('2024-02-01')],
    ['name' => 'Appeal', 'date' => Carbon::parse('2024-02-15')],
    ['name' => 'Decision', 'date' => Carbon::parse('2024-03-01')],
];

$result = $validator->validateTimeline($events);
if ($result->isValid()) {
    echo "Timeline is chronological";
}

// Invalid timeline
$events = [
    ['name' => 'Filing', 'date' => Carbon::parse('2024-03-01')],
    ['name' => 'Hearing', 'date' => Carbon::parse('2024-02-01')], // Before filing!
];

$result = $validator->validateTimeline($events);
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ["Event 'Hearing' occurs before 'Filing' but should be chronological"]
}
```

### Example 2: Validate Case Dates

```php
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

$validator = new TemporalConsistencyValidator();

// Valid case with hearings
$case = (object)[
    'filing_date' => Carbon::parse('2024-01-01'),
    'hearing_dates' => [
        Carbon::parse('2024-02-01'),
        Carbon::parse('2024-02-15'),
    ],
    'decision_date' => Carbon::parse('2024-03-01'),
];

$result = $validator->validateCaseDates($case);
if ($result->isValid()) {
    echo "Case dates are valid";
}

// Invalid: decision before filing
$case = (object)[
    'filing_date' => Carbon::parse('2024-03-01'),
    'decision_date' => Carbon::parse('2024-01-01'),
];

$result = $validator->validateCaseDates($case);
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Decision date cannot be before filing date']
}

// Invalid: hearing after decision
$case = (object)[
    'filing_date' => Carbon::parse('2024-01-01'),
    'hearing_dates' => [
        Carbon::parse('2024-04-01'), // After decision!
    ],
    'decision_date' => Carbon::parse('2024-03-01'),
];

$result = $validator->validateCaseDates($case);
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Hearing date cannot be after decision date']
}
```

### Example 3: Validate Evidence Dates

```php
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

$validator = new TemporalConsistencyValidator();

// Valid: evidence collected during case
$evidence = (object)[
    'collection_date' => Carbon::parse('2024-02-01'),
];

$case = (object)[
    'filing_date' => Carbon::parse('2024-01-01'),
    'decision_date' => Carbon::parse('2024-03-01'),
];

$result = $validator->validateEvidenceDates($evidence, $case);
if ($result->isValid()) {
    echo "Evidence dates are valid";
}

// Invalid: evidence before case filing
$evidence = (object)[
    'collection_date' => Carbon::parse('2023-12-15'),
];

$result = $validator->validateEvidenceDates($evidence, $case);
if (!$result->isValid()) {
    print_r($result->errors());
    // Output: ['Evidence collection date is before case filing date']
}

// Valid: ongoing case without decision
$case = (object)[
    'filing_date' => Carbon::parse('2024-01-01'),
    // No decision_date yet
];

$evidence = (object)[
    'collection_date' => Carbon::parse('2024-02-01'),
];

$result = $validator->validateEvidenceDates($evidence, $case);
// Will pass - only validates against filing date
```

### Example 4: Validate Complete Test Data

```php
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

$validator = new TemporalConsistencyValidator();

$testData = [
    'case' => (object)[
        'filing_date' => Carbon::parse('2024-01-01'),
        'hearing_dates' => [
            Carbon::parse('2024-02-01'),
        ],
        'decision_date' => Carbon::parse('2024-03-01'),
    ],
    'events' => [
        ['name' => 'Filing', 'date' => Carbon::parse('2024-01-01')],
        ['name' => 'Hearing', 'date' => Carbon::parse('2024-02-01')],
        ['name' => 'Decision', 'date' => Carbon::parse('2024-03-01')],
    ],
    'evidence' => (object)[
        'collection_date' => Carbon::parse('2024-01-15'),
    ],
];

$result = $validator->validate($testData);
if ($result->isValid()) {
    echo "All temporal validations passed!";
}
```

## Using Both Validators Together

```php
use Tests\TestData\Validators\LegalDataValidator;
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

$legalValidator = new LegalDataValidator();
$temporalValidator = new TemporalConsistencyValidator();

// Complete test case validation
$testCase = [
    // Legal data
    'citation' => 'ZKP Čl. 208',
    'court' => 'county',
    'parent_court' => 'high',
    'case_type' => 'criminal',

    // Temporal data
    'case' => (object)[
        'filing_date' => Carbon::parse('2024-01-15'),
        'decision_date' => Carbon::parse('2024-03-20'),
    ],
];

// Validate legal aspects
$legalResult = $legalValidator->validate([
    'citation' => $testCase['citation'],
    'court' => $testCase['court'],
    'parent_court' => $testCase['parent_court'],
    'case_type' => $testCase['case_type'],
]);

// Validate temporal aspects
$temporalResult = $temporalValidator->validate([
    'case' => $testCase['case'],
]);

// Check both validations
if ($legalResult->isValid() && $temporalResult->isValid()) {
    echo "Test case is fully validated!";
} else {
    echo "Validation errors:\n";
    foreach (array_merge($legalResult->errors(), $temporalResult->errors()) as $error) {
        echo "- $error\n";
    }
}
```

## Integration with PHPUnit Tests

```php
use Tests\TestCase;
use Tests\TestData\Validators\LegalDataValidator;
use Tests\TestData\Validators\TemporalConsistencyValidator;
use Carbon\Carbon;

class MyTestCase extends TestCase
{
    private LegalDataValidator $legalValidator;
    private TemporalConsistencyValidator $temporalValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legalValidator = new LegalDataValidator();
        $this->temporalValidator = new TemporalConsistencyValidator();
    }

    public function test_creates_valid_test_case(): void
    {
        $testData = $this->createTestCase();

        // Validate legal accuracy
        $legalResult = $this->legalValidator->validate([
            'citation' => $testData['citation'],
            'court' => $testData['court'],
            'case_type' => $testData['case_type'],
        ]);

        $this->assertTrue($legalResult->isValid(),
            'Legal validation failed: ' . implode(', ', $legalResult->errors()));

        // Validate temporal consistency
        $temporalResult = $this->temporalValidator->validateCaseDates($testData['case']);

        $this->assertTrue($temporalResult->isValid(),
            'Temporal validation failed: ' . implode(', ', $temporalResult->errors()));
    }

    private function createTestCase(): array
    {
        return [
            'citation' => 'ZKP Čl. 208',
            'court' => 'municipal',
            'case_type' => 'criminal',
            'case' => (object)[
                'filing_date' => Carbon::parse('2024-01-01'),
                'decision_date' => Carbon::parse('2024-02-01'),
            ],
        ];
    }
}
```

## Validation Rules Summary

### LegalDataValidator Rules

1. **Citation Format**: Must match pattern "LAW Čl. NUMBER"
   - Valid laws: ZKP, KZ, Ustav RH
   - Example: "ZKP Čl. 12"

2. **Court Hierarchy**: Lower courts must be under higher courts
   - Hierarchy: municipal (1) < county (2) < high (3) < supreme (4)
   - Parent court must have higher level than child court

3. **Case Types**: Must be one of the valid types
   - Valid types: criminal, civil, commercial, labor

### TemporalConsistencyValidator Rules

1. **Timeline Order**: Events must be chronologically ordered
   - Each event must occur at or after the previous event

2. **Case Dates**: Must follow proper sequence
   - filing_date < hearing_dates < decision_date
   - All hearings must be after filing
   - All hearings must be before decision (if decision exists)

3. **Evidence Dates**: Must fall within case timeline
   - Evidence collection >= filing_date
   - Evidence collection <= decision_date (if decision exists)
