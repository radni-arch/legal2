<?php

namespace Tests\Unit\TestData\Builders;

use App\Models\LegalCase;
use Tests\TestCase;
use Tests\TestData\Builders\CriminalCaseScenarioBuilder;
use Tests\UsesTestDatabase;

class CriminalCaseScenarioBuilderTest extends TestCase
{
    use UsesTestDatabase;

    public function test_can_build_criminal_case_with_drug_charges(): void
    {
        $case = CriminalCaseScenarioBuilder::make()
            ->withDrugCharges()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertContains('criminal', $case->tags);
        $this->assertGreaterThan(0, $case->documents()->count());

        // Verify drug charge exists in documents
        $this->assertTrue(
            $case->documents()->where('content', 'LIKE', '%droga%')->exists(),
            'Expected to find drug-related content in case documents'
        );
    }

    public function test_can_build_criminal_case_with_home_search(): void
    {
        $case = CriminalCaseScenarioBuilder::make()
            ->withHomeSearch()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertGreaterThan(0, $case->documents()->count());

        // Verify home search document exists
        $this->assertTrue(
            $case->documents()->where('content', 'LIKE', '%pretres%')->exists(),
            'Expected to find home search (pretres) content in case documents'
        );
    }

    public function test_can_build_criminal_case_with_fabricated_evidence(): void
    {
        $case = CriminalCaseScenarioBuilder::make()
            ->withFabricatedEvidence()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertGreaterThan(0, $case->documents()->count());

        // Verify fabricated evidence markers exist
        $fabricatedDocs = $case->documents()
            ->where('content', 'LIKE', '%fabrikovan%')
            ->orWhere('content', 'LIKE', '%krivotvoreno%')
            ->count();

        $this->assertGreaterThan(0, $fabricatedDocs, 'Expected to find fabricated evidence indicators');
    }

    public function test_can_build_criminal_case_with_witnesses(): void
    {
        $witnessCount = 3;
        $case = CriminalCaseScenarioBuilder::make()
            ->withWitnesses($witnessCount)
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);

        // Verify witness documents exist
        $witnessDocs = $case->documents()->where('category', 'witness')->count();
        $this->assertEquals($witnessCount, $witnessDocs, "Expected {$witnessCount} witness documents");
    }

    public function test_can_build_complex_criminal_case_with_multiple_features(): void
    {
        $case = CriminalCaseScenarioBuilder::make()
            ->withDrugCharges()
            ->withHomeSearch()
            ->withWitnesses(2)
            ->withFabricatedEvidence()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertContains('criminal', $case->tags);

        // Should have multiple documents (drug charge + home search + 2 witnesses + fabricated evidence)
        $this->assertGreaterThanOrEqual(5, $case->documents()->count());

        // Verify each component exists
        $this->assertTrue($case->documents()->where('content', 'LIKE', '%droga%')->exists());
        $this->assertTrue($case->documents()->where('content', 'LIKE', '%pretres%')->exists());
        $this->assertEquals(2, $case->documents()->where('category', 'witness')->count());
    }

    public function test_builder_returns_fresh_instance_on_make(): void
    {
        $builder1 = CriminalCaseScenarioBuilder::make();
        $builder2 = CriminalCaseScenarioBuilder::make();

        $this->assertNotSame($builder1, $builder2, 'make() should return new instances');
    }

    public function test_can_build_basic_criminal_case_without_extras(): void
    {
        $case = CriminalCaseScenarioBuilder::make()->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertContains('criminal', $case->tags);
        $this->assertNotNull($case->case_number);
        $this->assertNotNull($case->filing_date);
    }
}
