<?php

namespace Tests\Unit\Enums;

use App\Enums\EkomDokumentType;
use App\Enums\EkomOtpravakStatus;
use App\Enums\EkomPagedOtpravakStatus;
use App\Enums\EkomPodnesakStatus;
use App\Enums\EkomPredmetStatus;
use App\Enums\EkomStrankaType;
use App\Enums\EkomSudskaRadnjaStatus;
use App\Enums\EkomValuta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EkomEnumsTest extends TestCase
{
    public function test_predmet_status_values(): void
    {
        $this->assertSame('U_RADU', EkomPredmetStatus::U_RADU->value);
        $this->assertSame('ARHIVIRAN', EkomPredmetStatus::ARHIVIRAN->value);
        $this->assertCount(2, EkomPredmetStatus::cases());
    }

    public function test_podnesak_status_values(): void
    {
        $this->assertSame('NACRT', EkomPodnesakStatus::NACRT->value);
        $this->assertSame('POSLAN', EkomPodnesakStatus::POSLAN->value);
        $this->assertCount(2, EkomPodnesakStatus::cases());
    }

    public function test_otpravak_status_values(): void
    {
        $this->assertSame('U_DOSTAVI', EkomOtpravakStatus::U_DOSTAVI->value);
        $this->assertSame('URUCEN', EkomOtpravakStatus::URUCEN->value);
        $this->assertSame('NEURUCEN', EkomOtpravakStatus::NEURUCEN->value);
        $this->assertCount(3, EkomOtpravakStatus::cases());
    }

    public function test_paged_otpravak_status_values(): void
    {
        $this->assertSame('PRIMLJEN', EkomPagedOtpravakStatus::PRIMLJEN->value);
        $this->assertSame('U_DOSTAVI', EkomPagedOtpravakStatus::U_DOSTAVI->value);
        $this->assertCount(2, EkomPagedOtpravakStatus::cases());
    }

    public function test_stranka_type_values(): void
    {
        $this->assertSame('FIZICKA_OSOBA', EkomStrankaType::FIZICKA_OSOBA->value);
        $this->assertSame('PRAVNA_OSOBA', EkomStrankaType::PRAVNA_OSOBA->value);
        $this->assertSame('TIJELO', EkomStrankaType::TIJELO->value);
        $this->assertCount(3, EkomStrankaType::cases());
    }

    public function test_dokument_type_values(): void
    {
        $this->assertSame('PODNESAK', EkomDokumentType::PODNESAK->value);
        $this->assertSame('PRILOG', EkomDokumentType::PRILOG->value);
        $this->assertSame('ODLUKA', EkomDokumentType::ODLUKA->value);
        $this->assertSame('ZAPISNIK', EkomDokumentType::ZAPISNIK->value);
        $this->assertSame('POZIV', EkomDokumentType::POZIV->value);
        $this->assertSame('UMETNUTI_PREDMET', EkomDokumentType::UMETNUTI_PREDMET->value);
        $this->assertCount(6, EkomDokumentType::cases());
    }

    public function test_sudska_radnja_status_values(): void
    {
        $this->assertSame('NEAKTIVNA', EkomSudskaRadnjaStatus::NEAKTIVNA->value);
        $this->assertSame('ODREDJENA', EkomSudskaRadnjaStatus::ODREDJENA->value);
        $this->assertSame('ODGODJENA', EkomSudskaRadnjaStatus::ODGODJENA->value);
        $this->assertSame('IZVRSENA', EkomSudskaRadnjaStatus::IZVRSENA->value);
        $this->assertCount(4, EkomSudskaRadnjaStatus::cases());
    }

    public function test_valuta_values(): void
    {
        $this->assertSame('EUR', EkomValuta::EUR->value);
        $this->assertSame('HRK', EkomValuta::HRK->value);
        $this->assertCount(2, EkomValuta::cases());
    }

    public function test_try_from_returns_enum_for_valid_value(): void
    {
        $this->assertSame(EkomPredmetStatus::U_RADU, EkomPredmetStatus::tryFrom('U_RADU'));
        $this->assertSame(EkomValuta::EUR, EkomValuta::tryFrom('EUR'));
        $this->assertSame(EkomDokumentType::ODLUKA, EkomDokumentType::tryFrom('ODLUKA'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(EkomPredmetStatus::tryFrom('INVALID'));
        $this->assertNull(EkomValuta::tryFrom('USD'));
        $this->assertNull(EkomDokumentType::tryFrom('NONEXISTENT'));
    }
}
