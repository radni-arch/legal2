<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\Podnesci\EkomPodnesakDTO;
use App\Services\Ekom\PodnesakService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PodnesakServiceTest extends TestCase
{
    private EkomApiClientInterface $client;

    private PodnesakService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(EkomApiClientInterface::class);
        $this->service = new PodnesakService($this->client);

        // Prevent actual logging during tests
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== S5-7: Signature Validation Tests ==========

    public function test_validate_for_sending_requires_prihvatljiv_signature(): void
    {
        $podnesak = $this->createPodnesakDTO([
            'status' => 'NACRT',
            'sadrzaj' => ['naziv' => 'doc.pdf'],
            'statusValidacijePotpisa' => 'NEPRIHVATLJIV',
        ]);

        $errors = $this->service->validateForSending($podnesak);

        $this->assertContains('Document must have valid digital signature', $errors);
    }

    public function test_validate_for_sending_passes_with_prihvatljiv_signature(): void
    {
        $podnesak = $this->createPodnesakDTO([
            'status' => 'NACRT',
            'sadrzaj' => ['naziv' => 'doc.pdf'],
            'statusValidacijePotpisa' => 'PRIHVATLJIV',
        ]);

        $errors = $this->service->validateForSending($podnesak);

        $this->assertNotContains('Document must have valid digital signature', $errors);
    }

    public function test_validate_for_sending_fails_without_signature(): void
    {
        $podnesak = $this->createPodnesakDTO([
            'status' => 'NACRT',
            'sadrzaj' => ['naziv' => 'doc.pdf'],
            'statusValidacijePotpisa' => null,
        ]);

        $errors = $this->service->validateForSending($podnesak);

        $this->assertContains('Document must have valid digital signature', $errors);
    }

    public function test_validate_for_sending_fails_if_not_draft(): void
    {
        $podnesak = $this->createPodnesakDTO([
            'status' => 'POSLAN',
            'sadrzaj' => ['naziv' => 'doc.pdf'],
            'statusValidacijePotpisa' => 'PRIHVATLJIV',
        ]);

        $errors = $this->service->validateForSending($podnesak);

        $this->assertContains('Submission is not in draft status', $errors);
    }

    public function test_validate_for_sending_fails_without_content(): void
    {
        $podnesak = $this->createPodnesakDTO([
            'status' => 'NACRT',
            'sadrzaj' => null,
            'statusValidacijePotpisa' => 'PRIHVATLJIV',
        ]);

        $errors = $this->service->validateForSending($podnesak);

        $this->assertContains('Submission has no content', $errors);
    }

    // ========== S5-8: razlogNeplacanjaId Send Logic Tests ==========

    public function test_send_to_court_requires_razlog_when_fee_unpaid_and_no_exemption(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithUnpaidFee());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('razlogNeplacanjaId required for unpaid fee');

        $this->service->sendToCourt(42);
    }

    public function test_send_to_court_sends_razlog_when_provided_for_unpaid_fee(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithUnpaidFee());

        $this->client
            ->expects($this->once())
            ->method('posaljiPodnesakNaSud')
            ->with(42, ['razlogNeplacanjaId' => 5]);

        $this->service->sendToCourt(42, 5);
    }

    public function test_send_to_court_no_razlog_needed_when_fully_paid(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithPaidFee());

        $this->client
            ->expects($this->once())
            ->method('posaljiPodnesakNaSud')
            ->with(42, []);

        $this->service->sendToCourt(42);
    }

    public function test_send_to_court_no_razlog_needed_when_no_fee(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithoutFee());

        $this->client
            ->expects($this->once())
            ->method('posaljiPodnesakNaSud')
            ->with(42, []);

        $this->service->sendToCourt(42);
    }

    public function test_send_to_court_no_razlog_needed_when_fully_exempt(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithFullExemption());

        $this->client
            ->expects($this->once())
            ->method('posaljiPodnesakNaSud')
            ->with(42, []);

        $this->service->sendToCourt(42);
    }

    public function test_send_to_court_requires_razlog_with_partial_exemption(): void
    {
        // 50% exemption still means ostatak > 0, so razlog needed
        $this->client
            ->expects($this->once())
            ->method('getPodnesak')
            ->with(42)
            ->willReturn($this->apiResponseWithPartialExemption());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('razlogNeplacanjaId required for unpaid fee');

        $this->service->sendToCourt(42);
    }

    // ========== Helper Methods ==========

    private function createPodnesakDTO(array $overrides): EkomPodnesakDTO
    {
        $defaults = [
            'id' => 42,
            'status' => 'NACRT',
            'sudId' => 10,
            'sudNaziv' => 'Opcinski sud',
            'predmetId' => null,
            'predmetOznaka' => null,
            'vrstaPostupkaId' => 5,
            'vrstaPodneskaId' => 7,
            'vrstaPodneskaOznaka' => 'TUZBA',
            'stranke' => [],
            'protustranke' => [],
            'prilozi' => [],
            'pristojba' => null,
            'prosljedjivanja' => [],
            'vrijemeKreiranja' => '2025-01-01T10:00:00',
            'vrijemeSlanja' => null,
            'sadrzaj' => null,
            'statusValidacijePotpisa' => null,
        ];

        return EkomPodnesakDTO::fromApiResponse(array_merge($defaults, $overrides));
    }

    private function apiResponseWithUnpaidFee(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'pristojba' => [
                'iznos' => 100.00,
                'placeno' => 0.00,
                'ostatak' => 100.00,
                'postotakOslobodjenja' => null,
            ],
        ];
    }

    private function apiResponseWithPaidFee(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'pristojba' => [
                'iznos' => 100.00,
                'placeno' => 100.00,
                'ostatak' => 0.00,
                'postotakOslobodjenja' => null,
            ],
        ];
    }

    private function apiResponseWithoutFee(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'pristojba' => null,
        ];
    }

    private function apiResponseWithFullExemption(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'pristojba' => [
                'iznos' => 100.00,
                'placeno' => 0.00,
                'ostatak' => 100.00,
                'postotakOslobodjenja' => 100.0,
            ],
        ];
    }

    private function apiResponseWithPartialExemption(): array
    {
        return [
            'id' => 42,
            'status' => 'NACRT',
            'pristojba' => [
                'iznos' => 100.00,
                'placeno' => 0.00,
                'ostatak' => 50.00,  // 50% remaining
                'postotakOslobodjenja' => 50.0,
            ],
        ];
    }
}
