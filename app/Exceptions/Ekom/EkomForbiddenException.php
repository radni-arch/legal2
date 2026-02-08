<?php

namespace App\Exceptions\Ekom;

use App\Exceptions\EkomApiException;

class EkomForbiddenException extends EkomApiException
{
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
}
