<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Neo4jUnavailable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $healthStatus,
        public string $error,
        public bool $constraintsExist = false,
        public bool $indexesExist = false,
    ) {}
}
