<?php

namespace App\Clients;

interface EkomApiClientInterface
{
    // Predmeti (Cases)
    public function listPredmeti(array $query): array;

    public function getPredmetById(int $id): array;

    public function getPredmetByParams(array $query): array;

    public function getOtpravciPredmeta(int $predmetId): array;

    public function downloadDostavnicaOtpravkaPredmeta(int $predmetId, int $otpravakId, string $saveToPath): string;

    public function downloadDokumentiPredmeta(int $predmetId, array $dokumentIds, string $saveToPath): string;

    // Do Not Disturb
    public function turnOnDoNotDisturbPredmet(int $predmetId): bool;

    public function turnOffDoNotDisturbPredmet(int $predmetId): bool;

    public function turnOnGeneralDoNotDisturb(): array;

    public function turnOffGeneralDoNotDisturb(): array;

    public function turnOffDoNotDisturbForAllPredmet(): void;

    // Podnesci (Submissions)
    public function listPodnesci(array $query): array;

    public function getPodnesak(int $id): array;

    public function createPodnesak(array $payload, array $filePaths): array;

    public function createPrilogPodneska(int $podnesakId, array $payload, string $filePath): int;

    public function posaljiPodnesakNaSud(int $podnesakId, array $payload): void;

    public function downloadObavijestOPrimitkuPodneska(int $id, string $saveToPath): string;

    public function downloadNalogZaPlacanjePristojbePodneska(int $id, string $saveToPath): string;

    public function downloadDokazUplateOslobodjenjaPristojbePodneska(int $id, string $saveToPath): string;

    // Podnesci (Submissions) - Extended
    public function deletePodnesak(int $id): void;

    public function getPristojbaPodneska(int $id): array;

    // Otpravci (Dispatches)
    public function listOtpravci(array $query): array;

    public function getOtpravakById(int $id): array;

    public function potvrdiPrimitakOtpravka(int $id): void;

    public function downloadPotvrdaPrimitkaOtpravka(int $id, string $saveToPath): string;

    public function downloadDokumentiOtpravka(int $id, array $dokumentIds, string $saveToPath): string;

    // Šifrarnici (Reference)
    public function getSudovi(): array;

    // Šifrarnici (Reference Data) - Extended
    public function getNaselja(): array;

    public function getDrzave(): array;

    public function getRazloziNeplacanjaPristojbe(): array;

    public function getOsnoveOslobodjenjaPristojbe(): array;

    // Šifrarnici - Court Procedure Types
    public function getVrstePostupakaZaSud(int $sudId): array;

    public function getVrstePodnesakaNoviPostupak(int $vrstaPostupkaId): array;

    public function getVrstePodnesakaPostojeciPredmet(int $vrstaPostupkaId): array;

    // Šifrarnici - Participant and Submitter Roles
    public function getUlogeSudionika(int $vrstaPostupkaId): array;

    public function getUlogePodnositelja(int $vrstaPostupkaId): array;

    // Šifrarnici - Fee Options
    public function getPristojbaDodatneOpcijeNoviPostupak(int $vrstaPostupkaId, int $vrstaPodneskaId): array;

    public function getPristojbaDodatneOpcijePostojeciPredmet(int $vrstaPostupkaId, int $vrstaPodneskaId): array;
}
