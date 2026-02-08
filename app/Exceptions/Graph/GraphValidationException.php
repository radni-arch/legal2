<?php

namespace App\Exceptions\Graph;

use App\Exceptions\GraphException;

class GraphValidationException extends GraphException
{
    private array $validationErrors;

    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->validationErrors = $errors;
    }

    public static function withErrors(array $errors): self
    {
        $message = 'Graph validation failed: ' . implode(', ', array_keys($errors));
        return new self($message, $errors);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
