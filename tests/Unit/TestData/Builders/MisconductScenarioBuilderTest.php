<?php

namespace Tests\Unit\TestData\Builders;

use App\Models\LegalCase;
use Tests\TestCase;
use Tests\TestData\Builders\MisconductScenarioBuilder;
use Tests\UsesTestDatabase;

class MisconductScenarioBuilderTest extends TestCase
{
    use UsesTestDatabase;

    public function test_can_build_case_with_backdated_documents(): void
    {
        $case = MisconductScenarioBuilder::make()
            ->withBackdatedDocuments()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertGreaterThan(0, $case->documents()->count());

        // Verify backdated documents exist (document date is before case filing date)
        $backdated = $case->documents()
            ->get()
            ->filter(function ($doc) use ($case) {
                return isset($doc->metadata['document_date'])
                    && Carbon\Carbon::parse($doc->metadata['document_date'])->lt($case->filing_date);
            });

        $this->assertGreaterThan(0, $backdated->count(), 'Expected to find backdated documents');
    }

    public function test_can_build_case_with_hidden_evidence(): void
    {
        $case = MisconductScenarioBuilder::make()
            ->withHiddenEvidence()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);

        // Verify hidden evidence markers exist
        $hiddenEvidence = $case->documents()
            ->where('content', 'LIKE', '%prikriveno%')
            ->orWhere('content', 'LIKE', '%zatajeno%')
            ->count();

        $this->assertGreaterThan(0, $hiddenEvidence, 'Expected to find hidden evidence indicators');
    }

    public function test_can_build_case_with_fabricated_probable_cause(): void
    {
        $case = MisconductScenarioBuilder::make()
            ->withFabricatedPC()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);

        // Verify fabricated PC markers
        $fabricatedPC = $case->documents()
            ->where('content', 'LIKE', '%fabrikovan%')
            ->orWhere('content', 'LIKE', '%lažni%')
            ->count();

        $this->assertGreaterThan(0, $fabricatedPC, 'Expected to find fabricated probable cause indicators');
    }

    public function test_can_build_complex_misconduct_case(): void
    {
        $case = MisconductScenarioBuilder::make()
            ->withBackdatedDocuments()
            ->withHiddenEvidence()
            ->withFabricatedPC()
            ->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertGreaterThanOrEqual(3, $case->documents()->count());

        // Verify case has misconduct tags
        $this->assertContains('misconduct', $case->tags ?? []);
    }

    public function test_builder_creates_criminal_case_by_default(): void
    {
        $case = MisconductScenarioBuilder::make()->build();

        $this->assertInstanceOf(LegalCase::class, $case);
        $this->assertContains('criminal', $case->tags);
    }

    public function test_builder_returns_fresh_instance_on_make(): void
    {
        $builder1 = MisconductScenarioBuilder::make();
        $builder2 = MisconductScenarioBuilder::make();

        $this->assertNotSame($builder1, $builder2);
    }

    public function test_misconduct_patterns_are_detectable(): void
    {
        $case = MisconductScenarioBuilder::make()
            ->withBackdatedDocuments()
            ->withHiddenEvidence()
            ->withFabricatedPC()
            ->build();

        // Verify metadata includes misconduct indicators
        $documents = $case->documents()->get();

        $documentsWithMisconductMarkers = $documents->filter(function ($doc) {
            return isset($doc->metadata['misconduct_type']) || isset($doc->metadata['suspicious']);
        });

        $this->assertGreaterThan(0, $documentsWithMisconductMarkers->count(),
            'Expected documents to have misconduct metadata markers');
    }
}
