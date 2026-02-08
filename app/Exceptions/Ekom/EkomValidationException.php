<?php

namespace App\Exceptions\Ekom;

use App\Exceptions\EkomApiException;
use Psr\Http\Message\ResponseInterface;

/**
 * Exception for e-Komunikacija API validation errors (400 responses).
 *
 * The API returns validation errors in this format:
 * {
 *     "id": "uuid",
 *     "messages": ["error1", "error2"]
 * }
 *
 * This exception provides typed access to the messages array for UI display.
 */
class EkomValidationException extends EkomApiException
{
    /**
     * @var array<string>
     */
    private readonly array $validationMessages;

    /**
     * @param  array<string>  $errorMessages  Validation error messages
     */
    public function __construct(
        int $statusCode,
        ?string $errorId = null,
        ?string $errorMessage = null,
        ?array $errorMessages = null,
        ?string $responseBody = null
    ) {
        parent::__construct(
            statusCode: $statusCode,
            errorId: $errorId,
            errorMessage: $errorMessage,
            errorMessages: $errorMessages,
            responseBody: $responseBody
        );

        $this->validationMessages = $errorMessages ?? [];
    }

    /**
     * Create from a PSR-7 response (typically 400 status).
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $errorId = null;
        $errorMessage = null;
        $errorMessages = [];

        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $errorId = $json['id'] ?? null;
                $errorMessage = $json['message'] ?? null;
                $errorMessages = $json['messages'] ?? [];
            }
        }

        return new self(
            statusCode: $status,
            errorId: $errorId,
            errorMessage: $errorMessage,
            errorMessages: $errorMessages,
            responseBody: $body
        );
    }

    /**
     * Create from a parent EkomApiException.
     */
    public static function fromParent(EkomApiException $parent): self
    {
        return new self(
            statusCode: $parent->statusCode,
            errorId: $parent->errorId,
            errorMessage: $parent->errorMessage,
            errorMessages: $parent->errorMessages,
            responseBody: $parent->responseBody
        );
    }

    /**
     * Get the typed array of validation error messages.
     *
     * @return array<string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    /**
     * Check if there are any validation errors.
     */
    public function hasValidationErrors(): bool
    {
        return count($this->validationMessages) > 0;
    }

    /**
     * Get the first validation error message, or null if none.
     */
    public function getFirstValidationMessage(): ?string
    {
        return $this->validationMessages[0] ?? null;
    }
}
