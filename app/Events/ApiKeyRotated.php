<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyRotated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $fromKeyId,
        public int $toKeyId,
        public string $reason,
        public string $provider
    ) {}
}
