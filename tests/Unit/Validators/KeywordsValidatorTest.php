<?php

namespace Tests\Unit\Validators;

use Tests\TestCase;
use App\Validators\KeywordsValidator;

class KeywordsValidatorTest extends TestCase
{
    public function test_validates_correct_structure(): void
    {
        $data = [
            'metadata' => [
                'name' => 'Test',
                'description' => 'Test description',
            ],
            'categories' => [
                [
                    'name' => 'Category 1',
                    'description' => 'Category description',
                    'queries' => [
                        ['q' => 'term1 AND term2', 'comment' => 'Test'],
                    ],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_fails_when_categories_missing(): void
    {
        $data = ['metadata' => ['name' => 'Test']];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertContains("Missing or invalid 'categories' array.", $result['errors']);
    }

    public function test_fails_when_categories_not_array(): void
    {
        $data = ['categories' => 'not an array'];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertContains("Missing or invalid 'categories' array.", $result['errors']);
    }

    public function test_warns_about_missing_category_name(): void
    {
        $data = [
            'categories' => [
                [
                    'queries' => [['q' => 'test AND query']],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn($e) => str_contains($e, "Missing 'name'"))
        );
    }

    public function test_warns_about_missing_queries_array(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn($e) => str_contains($e, "Missing or invalid 'queries' array"))
        );
    }

    public function test_warns_about_empty_query(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                    'queries' => [
                        ['q' => '', 'comment' => 'Empty query'],
                    ],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn($e) => str_contains($e, 'Empty query'))
        );
    }

    public function test_warns_about_or_operator(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                    'queries' => [
                        ['q' => 'term1 OR term2', 'comment' => 'Uses OR'],
                    ],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn($e) => str_contains($e, 'Contains OR operator'))
        );
    }

    public function test_warns_about_too_many_terms(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                    'queries' => [
                        ['q' => 'term1 AND term2 AND term3 AND term4 AND term5 AND term6 AND term7', 'comment' => 'Too long'],
                    ],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn($e) => str_contains($e, 'Too many terms'))
        );
    }

    public function test_handles_string_queries(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                    'queries' => ['simple AND query'],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_handles_query_key_variant(): void
    {
        $data = [
            'categories' => [
                [
                    'name' => 'Test Category',
                    'queries' => [
                        ['query' => 'term1 AND term2', 'comment' => 'Uses query key'],
                    ],
                ],
            ],
        ];

        $result = KeywordsValidator::validate($data);

        $this->assertTrue($result['valid']);
    }
}
