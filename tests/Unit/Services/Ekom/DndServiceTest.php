<?php

namespace Tests\Unit\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\DndResponseDTO;
use App\Services\Ekom\DndService;
use PHPUnit\Framework\TestCase;

class DndServiceTest extends TestCase
{
    private EkomApiClientInterface $client;

    private DndService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(EkomApiClientInterface::class);
        $this->service = new DndService($this->client);
    }

    public function test_turn_on_for_predmet_calls_client_and_returns_dto(): void
    {
        $this->client
            ->expects($this->once())
            ->method('turnOnDoNotDisturbPredmet')
            ->with(42)
            ->willReturn(true);

        $result = $this->service->turnOnForPredmet(42);

        $this->assertInstanceOf(DndResponseDTO::class, $result);
        $this->assertTrue($result->currentState);
        $this->assertSame('DND enabled', $result->message);
    }

    public function test_turn_off_for_predmet_calls_client_and_returns_dto(): void
    {
        $this->client
            ->expects($this->once())
            ->method('turnOffDoNotDisturbPredmet')
            ->with(99)
            ->willReturn(true);

        $result = $this->service->turnOffForPredmet(99);

        $this->assertInstanceOf(DndResponseDTO::class, $result);
        $this->assertTrue($result->currentState);
    }

    public function test_turn_on_global_returns_dto_with_affected_count(): void
    {
        $this->client
            ->expects($this->once())
            ->method('turnOnGeneralDoNotDisturb')
            ->willReturn(['affectedCount' => 3, 'message' => 'General DND on']);

        $result = $this->service->turnOnGlobal();

        $this->assertInstanceOf(DndResponseDTO::class, $result);
        $this->assertSame(3, $result->affectedCount);
        $this->assertSame('General DND on', $result->message);
    }

    public function test_turn_off_global_returns_dto_with_affected_count(): void
    {
        $this->client
            ->expects($this->once())
            ->method('turnOffGeneralDoNotDisturb')
            ->willReturn(['affectedCount' => 7, 'message' => 'General DND off']);

        $result = $this->service->turnOffGlobal();

        $this->assertInstanceOf(DndResponseDTO::class, $result);
        $this->assertSame(7, $result->affectedCount);
        $this->assertSame('General DND off', $result->message);
    }

    public function test_reset_all_calls_client(): void
    {
        $this->client
            ->expects($this->once())
            ->method('turnOffDoNotDisturbForAllPredmet');

        $this->service->resetAll();
    }
}
