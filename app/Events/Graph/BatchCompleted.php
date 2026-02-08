<?php

namespace App\Events\Graph;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $batchNumber,
        public int $totalBatches,
        public int $nodesCreated,
        public int $relationshipsCreated,
        public int $errors,
        public float $durationSeconds
    ) {}
}
