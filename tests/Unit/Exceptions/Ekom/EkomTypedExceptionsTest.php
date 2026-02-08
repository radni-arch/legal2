<?php

namespace Tests\Unit\Exceptions\Ekom;

use App\Exceptions\Ekom\EkomBadRequestException;
use App\Exceptions\Ekom\EkomForbiddenException;
use App\Exceptions\Ekom\EkomNotFoundException;
use App\Exceptions\Ekom\EkomServerException;
use App\Exceptions\Ekom\EkomTooManyRequestsException;
use App\Exceptions\Ekom\EkomUnauthorizedException;
use App\Exceptions\EkomApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EkomTypedExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{class-string<EkomApiException>, int, string}>
     */
    public static function exceptionClassProvider(): array
    {
        return [
            'bad_request' => [EkomBadRequestException::class, 400, 'Bad Request'],
            'unauthorized' => [EkomUnauthorizedException::class, 401, 'Unauthorized'],
            'forbidden' => [EkomForbiddenException::class, 403, 'Forbidden'],
            'not_found' => [EkomNotFoundException::class, 404, 'Not Found'],
            'too_many_requests' => [EkomTooManyRequestsException::class, 429, 'Too Many Requests'],
            'server_error' => [EkomServerException::class, 500, 'Server Error'],
        ];
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_exception_extends_ekom_api_exception(string $class, int $expectedCode, string $expectedMessagePart): void
    {
        $exception = new $class(
            statusCode: $expectedCode,
            errorMessage: 'test error'
        );

        $this->assertInstanceOf(EkomApiException::class, $exception);
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_exception_has_correct_status_code(string $class, int $expectedCode, string $expectedMessagePart): void
    {
        $exception = new $class(
            statusCode: $expectedCode,
            errorMessage: 'test error'
        );

        $this->assertSame($expectedCode, $exception->statusCode);
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_from_parent_factory_creates_typed_exception(string $class, int $expectedCode, string $expectedMessagePart): void
    {
        $parent = new EkomApiException(
            statusCode: $expectedCode,
            errorId: 'ERR-001',
            errorMessage: 'Something went wrong',
            errorMessages: ['detail 1', 'detail 2'],
            responseBody: '{"error": "test"}'
        );

        $typed = $class::fromParent($parent);

        $this->assertInstanceOf($class, $typed);
        $this->assertSame($expectedCode, $typed->statusCode);
        $this->assertSame('ERR-001', $typed->errorId);
        $this->assertSame('Something went wrong', $typed->errorMessage);
        $this->assertSame(['detail 1', 'detail 2'], $typed->errorMessages);
        $this->assertSame('{"error": "test"}', $typed->responseBody);
    }

    #[DataProvider('exceptionClassProvider')]
    public function test_from_parent_preserves_message_string(string $class, int $expectedCode, string $expectedMessagePart): void
    {
        $parent = new EkomApiException(
            statusCode: $expectedCode,
            errorId: null,
            errorMessage: 'API failure',
        );

        $typed = $class::fromParent($parent);

        $this->assertStringContainsString((string) $expectedCode, $typed->getMessage());
    }

    public function test_server_exception_works_with_502_status(): void
    {
        $parent = new EkomApiException(statusCode: 502, errorMessage: 'Bad Gateway');
        $typed = EkomServerException::fromParent($parent);

        $this->assertInstanceOf(EkomServerException::class, $typed);
        $this->assertSame(502, $typed->statusCode);
    }

    public function test_server_exception_works_with_503_status(): void
    {
        $parent = new EkomApiException(statusCode: 503, errorMessage: 'Service Unavailable');
        $typed = EkomServerException::fromParent($parent);

        $this->assertInstanceOf(EkomServerException::class, $typed);
        $this->assertSame(503, $typed->statusCode);
    }
}
