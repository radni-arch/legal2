<?php

namespace Tests\Unit\Exceptions\Ekom;

use App\Exceptions\Ekom\EkomValidationException;
use App\Exceptions\EkomApiException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class EkomValidationExceptionTest extends TestCase
{
    public function test_extends_ekom_api_exception(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'VALIDATION-001',
            errorMessages: ['Field X is required']
        );

        $this->assertInstanceOf(EkomApiException::class, $exception);
    }

    public function test_stores_typed_messages_array(): void
    {
        $messages = [
            'Field A is required',
            'Field B must be a number',
            'Field C is too long',
        ];

        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'VALIDATION-002',
            errorMessages: $messages
        );

        $this->assertSame($messages, $exception->getValidationMessages());
        $this->assertCount(3, $exception->getValidationMessages());
    }

    public function test_from_response_parses_json_with_messages_array(): void
    {
        $body = json_encode([
            'id' => 'abc-123',
            'messages' => [
                'Invalid vrstaPostupkaId',
                'Missing required field: sadrzaj',
            ],
        ]);

        $response = new Response(400, ['Content-Type' => 'application/json'], $body);

        $exception = EkomValidationException::fromResponse($response);

        $this->assertSame(400, $exception->statusCode);
        $this->assertSame('abc-123', $exception->errorId);
        $this->assertSame([
            'Invalid vrstaPostupkaId',
            'Missing required field: sadrzaj',
        ], $exception->getValidationMessages());
    }

    public function test_from_response_handles_single_message(): void
    {
        $body = json_encode([
            'id' => 'xyz-789',
            'message' => 'Request validation failed',
            'messages' => ['Field sudId is invalid'],
        ]);

        $response = new Response(400, ['Content-Type' => 'application/json'], $body);

        $exception = EkomValidationException::fromResponse($response);

        $this->assertSame('Request validation failed', $exception->errorMessage);
        $this->assertSame(['Field sudId is invalid'], $exception->getValidationMessages());
    }

    public function test_from_response_handles_empty_messages(): void
    {
        $body = json_encode([
            'id' => 'empty-001',
            'messages' => [],
        ]);

        $response = new Response(400, ['Content-Type' => 'application/json'], $body);

        $exception = EkomValidationException::fromResponse($response);

        $this->assertSame([], $exception->getValidationMessages());
    }

    public function test_from_response_handles_no_messages_key(): void
    {
        $body = json_encode([
            'id' => 'no-messages-001',
            'error' => 'Something went wrong',
        ]);

        $response = new Response(400, ['Content-Type' => 'application/json'], $body);

        $exception = EkomValidationException::fromResponse($response);

        $this->assertSame([], $exception->getValidationMessages());
    }

    public function test_from_response_preserves_response_body(): void
    {
        $body = json_encode([
            'id' => 'preserve-001',
            'messages' => ['Error 1'],
        ]);

        $response = new Response(400, ['Content-Type' => 'application/json'], $body);

        $exception = EkomValidationException::fromResponse($response);

        $this->assertSame($body, $exception->responseBody);
    }

    public function test_from_parent_factory_creates_validation_exception(): void
    {
        $parent = new EkomApiException(
            statusCode: 400,
            errorId: 'PARENT-001',
            errorMessage: 'Validation failed',
            errorMessages: ['Field A invalid', 'Field B missing'],
            responseBody: '{"id": "test"}'
        );

        $validation = EkomValidationException::fromParent($parent);

        $this->assertInstanceOf(EkomValidationException::class, $validation);
        $this->assertSame(400, $validation->statusCode);
        $this->assertSame('PARENT-001', $validation->errorId);
        $this->assertSame('Validation failed', $validation->errorMessage);
        $this->assertSame(['Field A invalid', 'Field B missing'], $validation->getValidationMessages());
        $this->assertSame('{"id": "test"}', $validation->responseBody);
    }

    public function test_exception_message_includes_validation_count(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'MSG-001',
            errorMessages: ['Error 1', 'Error 2', 'Error 3']
        );

        $this->assertStringContainsString('400', $exception->getMessage());
    }

    public function test_has_validation_errors_returns_true_when_messages_exist(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'HAS-001',
            errorMessages: ['At least one error']
        );

        $this->assertTrue($exception->hasValidationErrors());
    }

    public function test_has_validation_errors_returns_false_when_empty(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'EMPTY-001',
            errorMessages: []
        );

        $this->assertFalse($exception->hasValidationErrors());
    }

    public function test_get_first_validation_message_returns_first(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'FIRST-001',
            errorMessages: ['First error', 'Second error']
        );

        $this->assertSame('First error', $exception->getFirstValidationMessage());
    }

    public function test_get_first_validation_message_returns_null_when_empty(): void
    {
        $exception = new EkomValidationException(
            statusCode: 400,
            errorId: 'FIRST-002',
            errorMessages: []
        );

        $this->assertNull($exception->getFirstValidationMessage());
    }
}
