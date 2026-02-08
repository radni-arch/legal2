<?php

namespace App\Services\Ekom;

use App\Clients\EkomApiClientInterface;
use App\DTOs\Ekom\DndResponseDTO;

class DndService
{
    public function __construct(
        private readonly EkomApiClientInterface $client,
    ) {}

    /**
     * Turn on DND for a specific predmet (case).
     */
    public function turnOnForPredmet(int $predmetId): DndResponseDTO
    {
        $result = $this->client->turnOnDoNotDisturbPredmet($predmetId);

        return DndResponseDTO::fromToggleResponse($result);
    }

    /**
     * Turn off DND for a specific predmet (case).
     */
    public function turnOffForPredmet(int $predmetId): DndResponseDTO
    {
        $result = $this->client->turnOffDoNotDisturbPredmet($predmetId);

        return DndResponseDTO::fromToggleResponse($result);
    }

    /**
     * Turn on global DND.
     */
    public function turnOnGlobal(): DndResponseDTO
    {
        $data = $this->client->turnOnGeneralDoNotDisturb();

        return DndResponseDTO::fromGeneralResponse($data);
    }

    /**
     * Turn off global DND.
     */
    public function turnOffGlobal(): DndResponseDTO
    {
        $data = $this->client->turnOffGeneralDoNotDisturb();

        return DndResponseDTO::fromGeneralResponse($data);
    }

    /**
     * Reset (turn off) DND for all predmeti.
     */
    public function resetAll(): void
    {
        $this->client->turnOffDoNotDisturbForAllPredmet();
    }
}
