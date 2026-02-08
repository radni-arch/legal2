<?php

namespace App\Services\Analysis\Contracts;

use App\Models\CaseDocument;

interface DocumentAnalyzerInterface
{
    /**
     * @return array{results: array, metadata: array}
     */
    public function analyze(CaseDocument $document, string $text): array;

    public function type(): string;

    public function layer(): string;
}
