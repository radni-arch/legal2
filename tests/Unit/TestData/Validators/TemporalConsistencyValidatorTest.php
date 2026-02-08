<?php

namespace Tests\Unit\TestData\Validators;

use Carbon\Carbon;
use Tests\TestCase;
use Tests\TestData\Validators\TemporalConsistencyValidator;

class TemporalConsistencyValidatorTest extends TestCase
{
    public function test_validates_chronological_timeline(): void
    {
        $validator = new TemporalConsistencyValidator;

        $events = [
            ['name' => 'Filing', 'date' => Carbon::parse('2024-01-01')],
            ['name' => 'Hearing', 'date' => Carbon::parse('2024-02-01')],
            ['name' => 'Decision', 'date' => Carbon::parse('2024-03-01')],
        ];

        $result = $validator->validateTimeline($events);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_validates_timeline_with_same_dates(): void
    {
        $validator = new TemporalConsistencyValidator;

        $events = [
            ['name' => 'Filing', 'date' => Carbon::parse('2024-01-01 09:00:00')],
            ['name' => 'Hearing', 'date' => Carbon::parse('2024-01-01 14:00:00')],
        ];

        $result = $validator->validateTimeline($events);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_non_chronological_timeline(): void
    {
        $validator = new TemporalConsistencyValidator;

        $events = [
            ['name' => 'Filing', 'date' => Carbon::parse('2024-03-01')],
            ['name' => 'Hearing', 'date' => Carbon::parse('2024-02-01')], // Before filing!
            ['name' => 'Decision', 'date' => Carbon::parse('2024-01-01')],
        ];

        $result = $validator->validateTimeline($events);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors());
        $this->assertStringContainsString('chronological', $result->errors()[0]);
    }

    public function test_validates_empty_timeline(): void
    {
        $validator = new TemporalConsistencyValidator;

        $result = $validator->validateTimeline([]);

        $this->assertTrue($result->isValid());
    }

    public function test_validates_case_dates_in_correct_order(): void
    {
        $validator = new TemporalConsistencyValidator;

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateCaseDates($case);

        $this->assertTrue($result->isValid());
    }

    public function test_validates_case_with_hearing_dates(): void
    {
        $validator = new TemporalConsistencyValidator;

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'hearing_dates' => [
                Carbon::parse('2024-02-01'),
                Carbon::parse('2024-02-15'),
            ],
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateCaseDates($case);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_decision_before_filing(): void
    {
        $validator = new TemporalConsistencyValidator;

        $case = (object) [
            'filing_date' => Carbon::parse('2024-03-01'),
            'decision_date' => Carbon::parse('2024-01-01'), // Before filing!
        ];

        $result = $validator->validateCaseDates($case);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Decision date cannot be before filing date', $result->errors()[0]);
    }

    public function test_rejects_hearing_before_filing(): void
    {
        $validator = new TemporalConsistencyValidator;

        $case = (object) [
            'filing_date' => Carbon::parse('2024-02-01'),
            'hearing_dates' => [
                Carbon::parse('2024-01-15'), // Before filing!
            ],
        ];

        $result = $validator->validateCaseDates($case);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Hearing date cannot be before filing date', $result->errors()[0]);
    }

    public function test_rejects_hearing_after_decision(): void
    {
        $validator = new TemporalConsistencyValidator;

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'hearing_dates' => [
                Carbon::parse('2024-04-01'), // After decision!
            ],
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateCaseDates($case);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Hearing date cannot be after decision date', $result->errors()[0]);
    }

    public function test_validates_evidence_within_case_timeline(): void
    {
        $validator = new TemporalConsistencyValidator;

        $evidence = (object) [
            'collection_date' => Carbon::parse('2024-02-01'),
        ];

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateEvidenceDates($evidence, $case);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_evidence_before_case_filing(): void
    {
        $validator = new TemporalConsistencyValidator;

        $evidence = (object) [
            'collection_date' => Carbon::parse('2023-12-15'), // Before case filing!
        ];

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateEvidenceDates($evidence, $case);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Evidence collection date is before case filing date', $result->errors()[0]);
    }

    public function test_rejects_evidence_after_case_decision(): void
    {
        $validator = new TemporalConsistencyValidator;

        $evidence = (object) [
            'collection_date' => Carbon::parse('2024-04-01'), // After case decision!
        ];

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            'decision_date' => Carbon::parse('2024-03-01'),
        ];

        $result = $validator->validateEvidenceDates($evidence, $case);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Evidence collection date is after case decision date', $result->errors()[0]);
    }

    public function test_validates_evidence_without_decision_date(): void
    {
        $validator = new TemporalConsistencyValidator;

        $evidence = (object) [
            'collection_date' => Carbon::parse('2024-02-01'),
        ];

        $case = (object) [
            'filing_date' => Carbon::parse('2024-01-01'),
            // No decision_date yet (case still open)
        ];

        $result = $validator->validateEvidenceDates($evidence, $case);

        $this->assertTrue($result->isValid());
    }

    public function test_validate_method_runs_all_temporal_validations(): void
    {
        $validator = new TemporalConsistencyValidator;

        $data = [
            'case' => (object) [
                'filing_date' => Carbon::parse('2024-01-01'),
                'decision_date' => Carbon::parse('2024-03-01'),
            ],
            'events' => [
                ['name' => 'Filing', 'date' => Carbon::parse('2024-01-01')],
                ['name' => 'Decision', 'date' => Carbon::parse('2024-03-01')],
            ],
        ];

        $result = $validator->validate($data);

        $this->assertTrue($result->isValid());
    }

    public function test_validate_method_collects_all_temporal_errors(): void
    {
        $validator = new TemporalConsistencyValidator;

        $data = [
            'case' => (object) [
                'filing_date' => Carbon::parse('2024-03-01'),
                'decision_date' => Carbon::parse('2024-01-01'), // Decision before filing!
            ],
            'events' => [
                ['name' => 'Filing', 'date' => Carbon::parse('2024-03-01')],
                ['name' => 'Decision', 'date' => Carbon::parse('2024-01-01')], // Out of order!
            ],
        ];

        $result = $validator->validate($data);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThan(0, count($result->errors()));
    }
}
