<?php

namespace Tests\Unit\DTOs\Ekom;

use App\DTOs\Ekom\DndResponseDTO;
use PHPUnit\Framework\TestCase;

class DndResponseDTOTest extends TestCase
{
    public function test_from_toggle_response_with_true(): void
    {
        $dto = DndResponseDTO::fromToggleResponse(true);

        $this->assertNull($dto->affectedCount);
        $this->assertTrue($dto->currentState);
        $this->assertSame('DND enabled', $dto->message);
    }

    public function test_from_toggle_response_with_false(): void
    {
        $dto = DndResponseDTO::fromToggleResponse(false);

        $this->assertNull($dto->affectedCount);
        $this->assertFalse($dto->currentState);
        $this->assertSame('DND disabled', $dto->message);
    }

    public function test_from_general_response_with_array_data(): void
    {
        $data = [
            'affectedCount' => 5,
            'message' => 'General DND toggled',
        ];

        $dto = DndResponseDTO::fromGeneralResponse($data);

        $this->assertSame(5, $dto->affectedCount);
        $this->assertNull($dto->currentState);
        $this->assertSame('General DND toggled', $dto->message);
    }

    public function test_from_general_response_with_minimal_data(): void
    {
        $dto = DndResponseDTO::fromGeneralResponse([]);

        $this->assertNull($dto->affectedCount);
        $this->assertNull($dto->currentState);
        $this->assertNull($dto->message);
    }

    public function test_constructor_assigns_properties(): void
    {
        $dto = new DndResponseDTO(
            affectedCount: 10,
            currentState: true,
            message: 'Test message',
        );

        $this->assertSame(10, $dto->affectedCount);
        $this->assertTrue($dto->currentState);
        $this->assertSame('Test message', $dto->message);
    }
}
