<?php

namespace Tests\Unit\DTOs\Ekom\Otpravci;

use App\DTOs\Ekom\Otpravci\OtpravakDTO;
use Carbon\Carbon;
use Tests\TestCase;

class OtpravakDTOTest extends TestCase
{
    private function makeApiResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 101,
            'status' => 'U_DOSTAVI',
            'primatelj' => 'Ivica Ivic',
            'datumOtpreme' => '2026-01-15T10:00:00',
            'datumUrucenja' => null,
            'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            'primljenZbogIstekaRoka' => false,
            'dokumenti' => [
                [
                    'id' => 1,
                    'naziv' => 'Presuda.pdf',
                    'tip' => 'application/pdf',
                    'datum' => '2026-01-15',
                    'velicinaBajtovi' => 102400,
                ],
                [
                    'id' => 2,
                    'naziv' => 'Prilog.docx',
                    'tip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'datum' => '2026-01-15',
                    'velicinaBajtovi' => 51200,
                ],
            ],
        ], $overrides);
    }

    /** @test */
    public function it_creates_from_api_response_with_full_data(): void
    {
        $data = $this->makeApiResponse();
        $dto = OtpravakDTO::fromApiResponse($data);

        $this->assertSame(101, $dto->id);
        $this->assertSame('U_DOSTAVI', $dto->status);
        $this->assertSame('Ivica Ivic', $dto->primatelj);
        $this->assertSame('2026-01-15T10:00:00', $dto->datumOtpreme);
        $this->assertNull($dto->datumUrucenja);
        $this->assertSame('2026-02-10T23:59:59', $dto->zadnjiTrenutakZaPotvrduPrimitka);
        $this->assertFalse($dto->primljenZbogIstekaRoka);
        $this->assertCount(2, $dto->dokumenti);
    }

    /** @test */
    public function it_creates_from_api_response_with_minimal_data(): void
    {
        $data = [
            'id' => 50,
            'status' => 'URUCEN',
        ];
        $dto = OtpravakDTO::fromApiResponse($data);

        $this->assertSame(50, $dto->id);
        $this->assertSame('URUCEN', $dto->status);
        $this->assertNull($dto->primatelj);
        $this->assertNull($dto->datumOtpreme);
        $this->assertNull($dto->datumUrucenja);
        $this->assertNull($dto->zadnjiTrenutakZaPotvrduPrimitka);
        $this->assertFalse($dto->primljenZbogIstekaRoka);
        $this->assertEmpty($dto->dokumenti);
    }

    /** @test */
    public function it_maps_dokumenti_to_dokument_dtos(): void
    {
        $data = $this->makeApiResponse();
        $dto = OtpravakDTO::fromApiResponse($data);

        $this->assertCount(2, $dto->dokumenti);

        $dok = $dto->dokumenti[0];
        $this->assertInstanceOf(\App\DTOs\Ekom\DokumentDTO::class, $dok);
        $this->assertSame(1, $dok->id);
        $this->assertSame('Presuda.pdf', $dok->naziv);
        $this->assertSame('application/pdf', $dok->tip);
        $this->assertSame('2026-01-15', $dok->datum);
        $this->assertSame(102400, $dok->velicinaBajtovi);
    }

    /** @test */
    public function is_deadline_approaching_returns_true_when_within_threshold(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            ])
        );

        // ~12 hours remaining, threshold is 24 hours
        $this->assertTrue($dto->isDeadlineApproaching(24));

        Carbon::setTestNow();
    }

    /** @test */
    public function is_deadline_approaching_returns_false_when_far_away(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            ])
        );

        // ~9 days remaining, threshold is 24 hours
        $this->assertFalse($dto->isDeadlineApproaching(24));

        Carbon::setTestNow();
    }

    /** @test */
    public function is_deadline_approaching_returns_true_when_deadline_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-15T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            ])
        );

        // Deadline already passed - should be considered approaching (past)
        $this->assertTrue($dto->isDeadlineApproaching(24));

        Carbon::setTestNow();
    }

    /** @test */
    public function is_deadline_approaching_returns_false_when_no_deadline(): void
    {
        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => null,
            ])
        );

        $this->assertFalse($dto->isDeadlineApproaching(24));
    }

    /** @test */
    public function is_deadline_passed_returns_true_when_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-15T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            ])
        );

        $this->assertTrue($dto->isDeadlinePassed());

        Carbon::setTestNow();
    }

    /** @test */
    public function is_deadline_passed_returns_false_when_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-10T23:59:59',
            ])
        );

        $this->assertFalse($dto->isDeadlinePassed());

        Carbon::setTestNow();
    }

    /** @test */
    public function is_deadline_passed_returns_false_when_no_deadline(): void
    {
        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => null,
            ])
        );

        $this->assertFalse($dto->isDeadlinePassed());
    }

    /** @test */
    public function hours_until_deadline_returns_positive_for_future_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-11T12:00:00',
            ])
        );

        $hours = $dto->hoursUntilDeadline();
        $this->assertNotNull($hours);
        $this->assertEqualsWithDelta(24.0, $hours, 0.1);

        Carbon::setTestNow();
    }

    /** @test */
    public function hours_until_deadline_returns_negative_for_past_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12T12:00:00'));

        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => '2026-02-11T12:00:00',
            ])
        );

        $hours = $dto->hoursUntilDeadline();
        $this->assertNotNull($hours);
        $this->assertEqualsWithDelta(-24.0, $hours, 0.1);

        Carbon::setTestNow();
    }

    /** @test */
    public function hours_until_deadline_returns_null_when_no_deadline(): void
    {
        $dto = OtpravakDTO::fromApiResponse(
            $this->makeApiResponse([
                'zadnjiTrenutakZaPotvrduPrimitka' => null,
            ])
        );

        $this->assertNull($dto->hoursUntilDeadline());
    }
}
