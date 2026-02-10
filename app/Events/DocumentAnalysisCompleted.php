<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a document analysis layer completes for a document.
 *
 * Used to trigger downstream processing such as case-level AI analysis
 * after Layer 1 (extraction) completes.
 */
class DocumentAnalysisCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $caseId,
        public string $documentId,
        public string $layer,
    ) {}
}
