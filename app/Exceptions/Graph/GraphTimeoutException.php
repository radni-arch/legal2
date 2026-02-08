<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;

class GraphTimeoutException extends GraphException
{
    private int $timeoutSeconds;

    public function __construct(string $message, int $timeoutSeconds)
    {
        parent::__construct($message);
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public static function afterSeconds(int $seconds, string $queryDescription = ''): self
    {
        $desc = $queryDescription ? " ($queryDescription)" : '';
        return new self(
            "Graph query timed out after {$seconds} seconds{$desc}",
            $seconds
        );
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
