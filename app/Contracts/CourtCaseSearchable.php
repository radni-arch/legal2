<?php

namespace App\Contracts;

interface CourtCaseSearchable
{
    public function getCaseIdentifier(): string;

    public function getCaseName(): string;

    public function getCourtName(): string;

    public function getDecisionDate(): ?string;

    public function getFullText(): ?string;

    public function getSourceUrl(): string;

    public function toSearchableArray(): array;
}
