<?php

namespace Tests\Unit\DTOs\Defense;

use App\DTOs\Defense\DefenseFlag;
use PHPUnit\Framework\TestCase;

class DefenseFlagTest extends TestCase
{
    /** @test */
    public function it_creates_a_defense_flag_with_all_properties(): void
    {
        $flag = new DefenseFlag(
            tactic: 'chain_of_custody',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'Dugacak interval zapljena - vjestacenje',
            description: 'Izmedju zapljene i vjestacenja proslo je 120 dana.',
            legalBasis: 'cl. 250. ZKP, cl. 261-262. ZKP',
            echrBasis: null,
            evidence: [
                'seizure_date' => '2024-01-15',
                'analysis_date' => '2024-05-15',
                'gap_days' => 120,
            ],
            recommendedAction: 'Zatraziti dokumentaciju o lancu cuvanja.',
            confidence: 0.7,
            metadata: ['analyzer' => 'ChainOfCustodyAnalyzer'],
        );

        $this->assertEquals('chain_of_custody', $flag->tactic);
        $this->assertEquals('high', $flag->severity);
        $this->assertEquals('Dugacak interval zapljena - vjestacenje', $flag->title);
        $this->assertEquals('Izmedju zapljene i vjestacenja proslo je 120 dana.', $flag->description);
        $this->assertEquals('cl. 250. ZKP, cl. 261-262. ZKP', $flag->legalBasis);
        $this->assertNull($flag->echrBasis);
        $this->assertEquals(0.7, $flag->confidence);
        $this->assertArrayHasKey('seizure_date', $flag->evidence);
    }

    /** @test */
    public function it_provides_severity_constants(): void
    {
        $this->assertEquals('critical', DefenseFlag::SEVERITY_CRITICAL);
        $this->assertEquals('high', DefenseFlag::SEVERITY_HIGH);
        $this->assertEquals('medium', DefenseFlag::SEVERITY_MEDIUM);
        $this->assertEquals('low', DefenseFlag::SEVERITY_LOW);
        $this->assertEquals('info', DefenseFlag::SEVERITY_INFO);
    }

    /** @test */
    public function it_converts_to_array(): void
    {
        $flag = new DefenseFlag(
            tactic: 'fruit_of_poisonous_tree',
            severity: DefenseFlag::SEVERITY_CRITICAL,
            title: 'Plodovi otrovnog drveta',
            description: 'Dokazi proizasli iz nezakonite pretrage.',
            legalBasis: 'cl. 10. st. 2. t. 4. ZKP',
            echrBasis: 'Gafgen v. Germany [GC] (2010)',
            evidence: ['tainted_date' => '2024-01-10'],
            recommendedAction: 'Zahtijevati izdvajanje svih derivativnih dokaza.',
            confidence: 0.65,
        );

        $array = $flag->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('fruit_of_poisonous_tree', $array['tactic']);
        $this->assertEquals('critical', $array['severity']);
        $this->assertEquals('cl. 10. st. 2. t. 4. ZKP', $array['legal_basis']);
        $this->assertEquals('Gafgen v. Germany [GC] (2010)', $array['echr_basis']);
        $this->assertEquals(0.65, $array['confidence']);
        $this->assertArrayHasKey('evidence', $array);
        $this->assertArrayHasKey('metadata', $array);
    }

    /** @test */
    public function it_has_default_empty_metadata(): void
    {
        $flag = new DefenseFlag(
            tactic: 'test',
            severity: DefenseFlag::SEVERITY_INFO,
            title: 'Test',
            description: 'Test description',
            legalBasis: 'test',
            echrBasis: null,
            evidence: [],
            recommendedAction: 'None',
            confidence: 0.5,
        );

        $this->assertEquals([], $flag->metadata);
        $array = $flag->toArray();
        $this->assertEquals([], $array['metadata']);
    }
}
