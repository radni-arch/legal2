<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyExhausted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $taskType,
        public int $keysAttempted
    ) {}
}
