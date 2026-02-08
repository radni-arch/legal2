<?php

namespace Tests\Unit\DTOs\Analysis;

use App\DTOs\Analysis\CaseReference;
use App\DTOs\Analysis\CaseReferenceCollection;
use PHPUnit\Framework\TestCase;

class CaseReferenceCollectionTest extends TestCase
{
    /** @test */
    public function it_initializes_with_empty_arrays(): void
    {
        $collection = new CaseReferenceCollection();

        $this->assertEquals([], $collection->klasa);
        $this->assertEquals([], $collection->urbroj);
        $this->assertEquals([], $collection->broj);
        $this->assertEquals([], $collection->caseNumbers);
        $this->assertEquals([], $collection->klasaUrbrojPairs);
    }

    /** @test */
    public function it_returns_all_references_merged(): void
    {
        $collection = new CaseReferenceCollection();

        $klasaRef = new CaseReference(
            type: 'klasa',
            value: 'KLASA: 034-02/25-01/5',
            rawMatch: 'KLASA: 034-02/25-01/5',
            subType: null,
            context: 'context',
            position: 10,
            mentions: 1,
        );

        $urbrojRef = new CaseReference(
            type: 'urbroj',
            value: 'URBROJ: 511-01-02-03-20-1',
            rawMatch: 'URBROJ: 511-01-02-03-20-1',
            subType: 'mup_policija',
            context: 'context',
            position: 50,
            mentions: 1,
        );

        $caseRef = new CaseReference(
            type: 'case_number',
            value: 'K-123/2024',
            rawMatch: 'K-123/2024',
            subType: 'kazneni',
            context: 'context',
            position: 100,
            mentions: 1,
        );

        $collection->klasa = [$klasaRef];
        $collection->urbroj = [$urbrojRef];
        $collection->caseNumbers = [$caseRef];

        $all = $collection->all();

        $this->assertCount(3, $all);
        $this->assertContains($klasaRef, $all);
        $this->assertContains($urbrojRef, $all);
        $this->assertContains($caseRef, $all);
    }

    /** @test */
    public function it_returns_unique_values_by_type(): void
    {
        $collection = new CaseReferenceCollection();

        $collection->klasa = [
            new CaseReference('klasa', 'KLASA: 034-02/25-01/5', 'KLASA: 034-02/25-01/5', null, 'c', 10, 1),
            new CaseReference('klasa', 'KLASA: 034-02/25-01/5', 'K L A S A: 034-02/25-01/5', null, 'c', 50, 1), // duplicate value
            new CaseReference('klasa', 'KLASA: 034-02/25-01/6', 'KLASA: 034-02/25-01/6', null, 'c', 90, 1),
        ];

        $collection->caseNumbers = [
            new CaseReference('case_number', 'K-123/2024', 'K-123/2024', 'kazneni', 'c', 100, 1),
            new CaseReference('case_number', 'K-123/2024', 'K-123/2024', 'kazneni', 'c', 200, 1), // duplicate
        ];

        $unique = $collection->uniqueValues();

        $this->assertCount(2, $unique['klasa']);
        $this->assertContains('KLASA: 034-02/25-01/5', $unique['klasa']);
        $this->assertContains('KLASA: 034-02/25-01/6', $unique['klasa']);

        $this->assertCount(1, $unique['case_numbers']);
        $this->assertContains('K-123/2024', $unique['case_numbers']);
    }

    /** @test */
    public function it_groups_case_numbers_by_type(): void
    {
        $collection = new CaseReferenceCollection();

        $collection->caseNumbers = [
            new CaseReference('case_number', 'K-123/2024', 'K-123/2024', 'kazneni', 'c', 100, 1),
            new CaseReference('case_number', 'Kz-456/2024', 'Kz-456/2024', 'kazneni', 'c', 150, 1),
            new CaseReference('case_number', 'Pp Prz-74/2025', 'Pp Prz-74/2025', 'prekrsajni', 'c', 200, 1),
            new CaseReference('case_number', 'DO-111/2024', 'DO-111/2024', 'dorh', 'c', 250, 1),
        ];

        $byType = $collection->caseNumbersByType();

        $this->assertArrayHasKey('kazneni', $byType);
        $this->assertArrayHasKey('prekrsajni', $byType);
        $this->assertArrayHasKey('dorh', $byType);

        $this->assertCount(2, $byType['kazneni']);
        $this->assertCount(1, $byType['prekrsajni']);
        $this->assertCount(1, $byType['dorh']);
    }

    /** @test */
    public function it_handles_null_subtype_in_case_numbers_by_type(): void
    {
        $collection = new CaseReferenceCollection();

        $collection->caseNumbers = [
            new CaseReference('case_number', 'Unknown-123', 'Unknown-123', null, 'c', 100, 1),
        ];

        $byType = $collection->caseNumbersByType();

        $this->assertArrayHasKey('unknown', $byType);
        $this->assertCount(1, $byType['unknown']);
    }

    /** @test */
    public function it_converts_to_array_with_all_data(): void
    {
        $collection = new CaseReferenceCollection();

        $collection->klasa = [
            new CaseReference('klasa', 'KLASA: 034-02/25-01/5', 'KLASA: 034-02/25-01/5', null, 'ctx', 10, 2),
        ];

        $collection->klasaUrbrojPairs = [
            '034-02/25-01/5' => '511-01-02-03-20-1',
        ];

        $array = $collection->toArray();

        $this->assertArrayHasKey('klasa', $array);
        $this->assertArrayHasKey('urbroj', $array);
        $this->assertArrayHasKey('broj', $array);
        $this->assertArrayHasKey('case_numbers', $array);
        $this->assertArrayHasKey('klasa_urbroj_pairs', $array);
        $this->assertArrayHasKey('unique_values', $array);
        $this->assertArrayHasKey('case_numbers_by_type', $array);

        $this->assertCount(1, $array['klasa']);
        $this->assertEquals('KLASA: 034-02/25-01/5', $array['klasa'][0]['value']);
        $this->assertEquals(['034-02/25-01/5' => '511-01-02-03-20-1'], $array['klasa_urbroj_pairs']);
    }
}
