<?php

namespace Tests\Unit\TestData\Validators;

use Tests\TestCase;
use Tests\TestData\Validators\LegalDataValidator;

class LegalDataValidatorTest extends TestCase
{
    public function test_validates_correct_zkp_citation(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCitation('ZKP Čl. 12');

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_validates_correct_kz_citation(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCitation('KZ Čl. 87');

        $this->assertTrue($result->isValid());
    }

    public function test_validates_correct_ustav_citation(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCitation('Ustav RH Čl. 29');

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_invalid_citation_format(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCitation('Invalid Citation');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors());
        $this->assertStringContainsString('Invalid citation format', $result->errors()[0]);
    }

    public function test_rejects_citation_without_article_marker(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCitation('ZKP 12');

        $this->assertFalse($result->isValid());
    }

    public function test_validates_court_hierarchy_county_under_high(): void
    {
        $validator = new LegalDataValidator;

        // County court under High court is valid
        $result = $validator->validateCourtHierarchy('county', 'high');

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_validates_court_hierarchy_municipal_under_county(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCourtHierarchy('municipal', 'county');

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_invalid_court_hierarchy_supreme_under_county(): void
    {
        $validator = new LegalDataValidator;

        // Supreme court under County court is invalid
        $result = $validator->validateCourtHierarchy('supreme', 'county');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Invalid court hierarchy', $result->errors()[0]);
    }

    public function test_rejects_unknown_court_level(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCourtHierarchy('district', 'high');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Unknown court level', $result->errors()[0]);
    }

    public function test_validates_court_without_parent(): void
    {
        $validator = new LegalDataValidator;

        // Top-level court without parent is valid
        $result = $validator->validateCourtHierarchy('supreme');

        $this->assertTrue($result->isValid());
    }

    public function test_validates_criminal_case_type(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCaseType('criminal');

        $this->assertTrue($result->isValid());
    }

    public function test_validates_civil_case_type(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCaseType('civil');

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_invalid_case_type(): void
    {
        $validator = new LegalDataValidator;

        $result = $validator->validateCaseType('traffic');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Invalid case type', $result->errors()[0]);
    }

    public function test_validate_method_runs_all_validations(): void
    {
        $validator = new LegalDataValidator;

        $data = [
            'citation' => 'ZKP Čl. 12',
            'court' => 'county',
            'parent_court' => 'high',
            'case_type' => 'criminal',
        ];

        $result = $validator->validate($data);

        $this->assertTrue($result->isValid());
    }

    public function test_validate_method_collects_all_errors(): void
    {
        $validator = new LegalDataValidator;

        $data = [
            'citation' => 'Invalid',
            'court' => 'supreme',
            'parent_court' => 'county',
            'case_type' => 'invalid',
        ];

        $result = $validator->validate($data);

        $this->assertFalse($result->isValid());
        $this->assertCount(3, $result->errors()); // citation, court hierarchy, case type
    }
}
