<?php

namespace Tests\Unit\DTOs\Analysis;

use App\DTOs\Analysis\CaseReference;
use PHPUnit\Framework\TestCase;

class CaseReferenceTest extends TestCase
{
    /** @test */
    public function it_creates_case_reference_with_all_properties(): void
    {
        $reference = new CaseReference(
            type: 'klasa',
            value: 'KLASA: UP/I-034-02/20-01/123',
            rawMatch: 'KLASA: UP/I-034-02/20-01/123',
            subType: null,
            context: 'Some surrounding context text',
            position: 150,
            mentions: 2,
            pairedWith: 'URBROJ: 511-01-02-03-20-1'
        );

        $this->assertEquals('klasa', $reference->type);
        $this->assertEquals('KLASA: UP/I-034-02/20-01/123', $reference->value);
        $this->assertEquals('KLASA: UP/I-034-02/20-01/123', $reference->rawMatch);
        $this->assertNull($reference->subType);
        $this->assertEquals('Some surrounding context text', $reference->context);
        $this->assertEquals(150, $reference->position);
        $this->assertEquals(2, $reference->mentions);
        $this->assertEquals('URBROJ: 511-01-02-03-20-1', $reference->pairedWith);
    }

    /** @test */
    public function it_creates_case_reference_with_subtype(): void
    {
        $reference = new CaseReference(
            type: 'case_number',
            value: 'K-123/2024',
            rawMatch: 'K-123/2024',
            subType: 'kazneni',
            context: 'U predmetu broj K-123/2024',
            position: 50,
            mentions: 1,
        );

        $this->assertEquals('case_number', $reference->type);
        $this->assertEquals('kazneni', $reference->subType);
        $this->assertNull($reference->pairedWith);
    }

    /** @test */
    public function it_converts_to_array(): void
    {
        $reference = new CaseReference(
            type: 'urbroj',
            value: 'URBROJ: 511-01-02-03-20-1',
            rawMatch: 'U R B R O J: 511-01-02-03-20-1',
            subType: 'mup_policija',
            context: 'Context around urbroj',
            position: 200,
            mentions: 3,
            pairedWith: 'KLASA: UP/I-034-02/20-01/123'
        );

        $array = $reference->toArray();

        $this->assertEquals([
            'type' => 'urbroj',
            'value' => 'URBROJ: 511-01-02-03-20-1',
            'raw_match' => 'U R B R O J: 511-01-02-03-20-1',
            'sub_type' => 'mup_policija',
            'context' => 'Context around urbroj',
            'position' => 200,
            'mentions' => 3,
            'paired_with' => 'KLASA: UP/I-034-02/20-01/123',
        ], $array);
    }

    /** @test */
    public function it_handles_null_optional_fields_in_array(): void
    {
        $reference = new CaseReference(
            type: 'broj',
            value: 'Broj: 511-07-11-K-51/2025',
            rawMatch: 'Broj: 511-07-11-K-51/2025',
            subType: null,
            context: null,
            position: 0,
            mentions: 1,
        );

        $array = $reference->toArray();

        $this->assertNull($array['sub_type']);
        $this->assertNull($array['context']);
        $this->assertNull($array['paired_with']);
    }
}
