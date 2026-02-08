<?php

namespace Tests\Unit\Services\Ekom\Builders;

use App\Services\Ekom\Builders\CreateEkomPodnesakRequestBuilder;
use App\Services\Ekom\Builders\CreateStrankaRequestBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CreateEkomPodnesakRequestBuilderTest extends TestCase
{
    private function minimalNewProceedingBuilder(): CreateEkomPodnesakRequestBuilder
    {
        return (new CreateEkomPodnesakRequestBuilder())
            ->forCourt(1)
            ->asNewProceeding(10, 20)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test content'))
            ->addStranka(
                CreateStrankaRequestBuilder::fizickaOsoba()
                    ->withName('Ivan', 'Horvat')
                    ->withOib('12345678901')
                    ->build()
            );
    }

    private function minimalExistingCaseBuilder(): CreateEkomPodnesakRequestBuilder
    {
        return (new CreateEkomPodnesakRequestBuilder())
            ->forCourt(1)
            ->forExistingCase(999)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test content'));
    }

    // ===========================================
    // S4-9: External Reference Tests
    // ===========================================

    public function test_external_ref_fields_are_included_in_build(): void
    {
        $payload = $this->minimalNewProceedingBuilder()
            ->withExternalRef('OU-ZAGREB', 'PRED-2024-001')
            ->build();

        $this->assertArrayHasKey('vanjskaUstrojstvenaJedinica', $payload);
        $this->assertArrayHasKey('vanjskiPredmet', $payload);
        $this->assertSame('OU-ZAGREB', $payload['vanjskaUstrojstvenaJedinica']);
        $this->assertSame('PRED-2024-001', $payload['vanjskiPredmet']);
    }

    public function test_external_ref_is_optional(): void
    {
        $payload = $this->minimalNewProceedingBuilder()->build();

        $this->assertArrayNotHasKey('vanjskaUstrojstvenaJedinica', $payload);
        $this->assertArrayNotHasKey('vanjskiPredmet', $payload);
    }

    public function test_external_ref_works_with_existing_case(): void
    {
        $payload = $this->minimalExistingCaseBuilder()
            ->withExternalRef('OU-SPLIT', 'PRED-2024-002')
            ->build();

        $this->assertSame('OU-SPLIT', $payload['vanjskaUstrojstvenaJedinica']);
        $this->assertSame('PRED-2024-002', $payload['vanjskiPredmet']);
    }

    // ===========================================
    // S4-10: Dual Submitter Identification Tests
    // ===========================================

    public function test_submitter_ordinal_for_new_proceeding(): void
    {
        $payload = $this->minimalNewProceedingBuilder()
            ->withSubmitterOrdinal(1)
            ->build();

        $this->assertArrayHasKey('podnositeljRbr', $payload);
        $this->assertSame(1, $payload['podnositeljRbr']);
        $this->assertArrayNotHasKey('podnositeljSlobodanUnos', $payload);
    }

    public function test_submitter_ordinal_requires_positive_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Submitter ordinal (podnositeljRbr) must be 1 or greater');

        $this->minimalNewProceedingBuilder()
            ->withSubmitterOrdinal(0)
            ->build();
    }

    public function test_submitter_name_for_existing_case(): void
    {
        $payload = $this->minimalExistingCaseBuilder()
            ->withSubmitterName('Odvjetnik Ivan Petrovic')
            ->build();

        $this->assertArrayHasKey('podnositeljSlobodanUnos', $payload);
        $this->assertSame('Odvjetnik Ivan Petrovic', $payload['podnositeljSlobodanUnos']);
        $this->assertArrayNotHasKey('podnositeljRbr', $payload);
    }

    public function test_submitter_ordinal_rejected_for_existing_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Submitter ordinal (podnositeljRbr) cannot be used for existing case');

        $this->minimalExistingCaseBuilder()
            ->withSubmitterOrdinal(1)
            ->build();
    }

    public function test_submitter_name_rejected_for_new_proceeding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Submitter free-text name (podnositeljSlobodanUnos) cannot be used for new proceeding');

        $this->minimalNewProceedingBuilder()
            ->withSubmitterName('Should not work')
            ->build();
    }

    // ===========================================
    // S4-11: Dispute Value Tests
    // ===========================================

    public function test_dispute_value_is_included_in_build(): void
    {
        $payload = $this->minimalNewProceedingBuilder()
            ->withDisputeValue(50000.50)
            ->build();

        $this->assertArrayHasKey('vrijednostPredmetaSpora', $payload);
        $this->assertSame(50000.50, $payload['vrijednostPredmetaSpora']);
    }

    public function test_dispute_value_is_optional(): void
    {
        $payload = $this->minimalNewProceedingBuilder()->build();

        $this->assertArrayNotHasKey('vrijednostPredmetaSpora', $payload);
    }

    public function test_dispute_value_accepts_integer(): void
    {
        $payload = $this->minimalNewProceedingBuilder()
            ->withDisputeValue(10000)
            ->build();

        $this->assertSame(10000.0, $payload['vrijednostPredmetaSpora']);
    }

    // ===========================================
    // S4-12: Not My Case Flag Tests
    // ===========================================

    public function test_confirm_not_my_case_sets_flag(): void
    {
        $payload = $this->minimalExistingCaseBuilder()
            ->confirmNotMyCase()
            ->build();

        $this->assertArrayHasKey('potvrdaPredmetaKojiNijeMoj', $payload);
        $this->assertTrue($payload['potvrdaPredmetaKojiNijeMoj']);
    }

    public function test_not_my_case_flag_is_optional(): void
    {
        $payload = $this->minimalExistingCaseBuilder()->build();

        $this->assertArrayNotHasKey('potvrdaPredmetaKojiNijeMoj', $payload);
    }

    public function test_not_my_case_flag_works_with_new_proceeding(): void
    {
        // This flag can be used in both modes
        $payload = $this->minimalNewProceedingBuilder()
            ->confirmNotMyCase()
            ->build();

        $this->assertTrue($payload['potvrdaPredmetaKojiNijeMoj']);
    }

    // ===========================================
    // S4-15: Existing Case Empty Arrays Tests
    // ===========================================

    public function test_existing_case_serializes_empty_stranke_array(): void
    {
        $payload = $this->minimalExistingCaseBuilder()->build();

        $this->assertArrayHasKey('stranke', $payload);
        $this->assertArrayHasKey('protustranke', $payload);
        $this->assertArrayHasKey('prilozi', $payload);

        $this->assertSame([], $payload['stranke']);
        $this->assertSame([], $payload['protustranke']);
        $this->assertSame([], $payload['prilozi']);
    }

    public function test_existing_case_rejects_non_empty_stranke(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Existing case cannot have parties (stranke)');

        (new CreateEkomPodnesakRequestBuilder())
            ->forCourt(1)
            ->forExistingCase(999)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test'))
            ->addStranka(
                CreateStrankaRequestBuilder::fizickaOsoba()
                    ->withName('Ivan', 'Horvat')
                    ->withOib('12345678901')
                    ->build()
            )
            ->build();
    }

    public function test_existing_case_rejects_non_empty_protustranke(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Existing case cannot have opposing parties (protustranke)');

        (new CreateEkomPodnesakRequestBuilder())
            ->forCourt(1)
            ->forExistingCase(999)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test'))
            ->addProtustranka(
                CreateStrankaRequestBuilder::fizickaOsoba()
                    ->withName('Ana', 'Maric')
                    ->withOib('98765432109')
                    ->build()
            )
            ->build();
    }

    // ===========================================
    // Existing Functionality Tests
    // ===========================================

    public function test_new_proceeding_requires_stranka(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one party (stranka) is required for new proceeding');

        (new CreateEkomPodnesakRequestBuilder())
            ->forCourt(1)
            ->asNewProceeding(10, 20)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test'))
            ->build();
    }

    public function test_new_proceeding_includes_vrsta_postupka_and_uloga(): void
    {
        $payload = $this->minimalNewProceedingBuilder()->build();

        $this->assertSame(10, $payload['vrstaPostupkaId']);
        $this->assertSame(20, $payload['ulogaPodnositeljaId']);
    }

    public function test_for_court_by_id(): void
    {
        $payload = $this->minimalNewProceedingBuilder()->build();

        $this->assertArrayHasKey('sudId', $payload);
        $this->assertSame(1, $payload['sudId']);
        $this->assertArrayNotHasKey('sudOznaka', $payload);
    }

    public function test_for_court_by_oznaka(): void
    {
        $payload = (new CreateEkomPodnesakRequestBuilder())
            ->forCourtByOznaka('OSCZ')
            ->asNewProceeding(10, 20)
            ->withSubmissionType(100)
            ->withContent('dokument.pdf', base64_encode('test'))
            ->addStranka(
                CreateStrankaRequestBuilder::fizickaOsoba()
                    ->withName('Ivan', 'Horvat')
                    ->withOib('12345678901')
                    ->build()
            )
            ->build();

        $this->assertArrayHasKey('sudOznaka', $payload);
        $this->assertSame('OSCZ', $payload['sudOznaka']);
        $this->assertArrayNotHasKey('sudId', $payload);
    }

    public function test_existing_case_includes_predmet_id(): void
    {
        $payload = $this->minimalExistingCaseBuilder()->build();

        $this->assertArrayHasKey('predmetId', $payload);
        $this->assertSame(999, $payload['predmetId']);
    }

    public function test_attachment_can_be_added(): void
    {
        $payload = $this->minimalNewProceedingBuilder()
            ->addPrilog(
                'Dokaz 1',
                'prilog.pdf',
                base64_encode('attachment content'),
                2,
                'Vazna napomena'
            )
            ->build();

        $this->assertArrayHasKey('prilozi', $payload);
        $this->assertCount(1, $payload['prilozi']);
        $this->assertSame('Dokaz 1', $payload['prilozi'][0]['opis']);
        $this->assertSame('Vazna napomena', $payload['prilozi'][0]['primjedba']);
    }
}
