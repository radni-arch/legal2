<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;
use Throwable;

class GraphQueryException extends GraphException
{
    private string $query;

    public function __construct(string $message, string $query = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->query = $query;
    }

    public static function forQuery(string $query, Throwable $previous): self
    {
        $preview = strlen($query) > 100 ? substr($query, 0, 100) . '...' : $query;
        return new self(
            "Graph query failed: {$previous->getMessage()} [Query: $preview]",
            $query,
            $previous
        );
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
