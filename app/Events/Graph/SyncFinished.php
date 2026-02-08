<?php

namespace App\Events\Graph;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncFinished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $totalDecisions,
        public int $totalNodesCreated,
        public int $totalRelationshipsCreated,
        public int $totalErrors,
        public float $totalDurationSeconds,
        public string $status // 'completed', 'partial', 'failed'
    ) {}
}
