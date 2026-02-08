<?php

namespace App\Events\Graph;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $batchNumber,
        public int $totalBatches,
        public int $batchSize,
        public array $decisionIds
    ) {}
}
