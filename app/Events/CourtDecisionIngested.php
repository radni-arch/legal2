<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourtDecisionIngested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $decisionId
    ) {}
}
