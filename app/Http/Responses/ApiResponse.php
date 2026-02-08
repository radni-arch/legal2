<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Standardized API Response Handler
 *
 * Provides consistent response formatting across all API endpoints with:
 * - Success/error status
 * - Request tracking (request_id)
 * - Timestamps
 * - API version
 * - Standardized error codes
 * - Pagination support
 */
class ApiResponse
{
    /**
     * API version
     */
    const API_VERSION = 'v1';

    /**
     * Create a standardized success response
     *
     * @param  mixed  $data  Response data
     * @param  string|null  $message  Optional success message
     * @param  int  $statusCode  HTTP status code (default: 200)
     * @param  array  $additionalMeta  Additional metadata
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $additionalMeta = []
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        $response['meta'] = static::buildMeta($additionalMeta);

        return response()->json($response, $statusCode);
    }

    /**
     * Create a standardized error response
     *
     * @param  string  $code  Error code (use ErrorCode constants)
     * @param  string  $message  Human-readable error message
     * @param  mixed  $details  Additional error details
     * @param  int  $statusCode  HTTP status code (default: 400)
     * @param  array  $additionalMeta  Additional metadata
     */
    public static function error(
        string $code,
        string $message,
        mixed $details = null,
        int $statusCode = 400,
        array $additionalMeta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        $response['meta'] = static::buildMeta($additionalMeta);

        return response()->json($response, $statusCode);
    }

    /**
     * Create a paginated success response
     *
     * @param  mixed  $data  Paginated data
     * @param  array  $pagination  Pagination metadata
     * @param  string|null  $message  Optional message
     * @param  array  $additionalMeta  Additional metadata
     */
    public static function paginated(
        mixed $data,
        array $pagination,
        ?string $message = null,
        array $additionalMeta = []
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        $response['data'] = $data;

        $response['pagination'] = [
            'total' => $pagination['total'] ?? 0,
            'count' => $pagination['count'] ?? 0,
            'per_page' => $pagination['per_page'] ?? 10,
            'current_page' => $pagination['current_page'] ?? 1,
            'total_pages' => $pagination['total_pages'] ?? 1,
        ];

        $response['meta'] = static::buildMeta($additionalMeta);

        return response()->json($response, 200);
    }

    /**
     * 200 OK - Standard success response
     */
    public static function ok(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return static::success($data, $message, 200, $meta);
    }

    /**
     * 201 Created - Resource created successfully
     *
     * @param  mixed  $data  Created resource
     */
    public static function created(mixed $data = null, ?string $message = 'Resource created successfully', array $meta = []): JsonResponse
    {
        return static::success($data, $message, 201, $meta);
    }

    /**
     * 202 Accepted - Request accepted for processing
     */
    public static function accepted(mixed $data = null, ?string $message = 'Request accepted for processing', array $meta = []): JsonResponse
    {
        return static::success($data, $message, 202, $meta);
    }

    /**
     * 204 No Content - Success with no response body
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 400 Bad Request - Validation error
     *
     * @param  mixed  $details  Validation errors
     */
    public static function validationError(string $message = 'Validation failed', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::VALIDATION_ERROR,
            $message,
            $details,
            400,
            $meta
        );
    }

    /**
     * 401 Unauthorized - Authentication required
     */
    public static function unauthorized(string $message = 'Unauthorized', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::UNAUTHORIZED,
            $message,
            $details,
            401,
            $meta
        );
    }

    /**
     * 403 Forbidden - Insufficient permissions
     */
    public static function forbidden(string $message = 'Forbidden', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::FORBIDDEN,
            $message,
            $details,
            403,
            $meta
        );
    }

    /**
     * 404 Not Found - Resource not found
     */
    public static function notFound(string $message = 'Resource not found', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::NOT_FOUND,
            $message,
            $details,
            404,
            $meta
        );
    }

    /**
     * 409 Conflict - Resource conflict
     */
    public static function conflict(string $message = 'Resource conflict', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::CONFLICT,
            $message,
            $details,
            409,
            $meta
        );
    }

    /**
     * 422 Unprocessable Entity - Semantic validation error
     */
    public static function unprocessable(string $message = 'Unprocessable entity', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::UNPROCESSABLE_ENTITY,
            $message,
            $details,
            422,
            $meta
        );
    }

    /**
     * 429 Too Many Requests - Rate limit exceeded
     *
     * @param  int  $retryAfter  Seconds until retry
     */
    public static function tooManyRequests(string $message = 'Too many requests', int $retryAfter = 60, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::RATE_LIMIT_EXCEEDED,
            $message,
            ['retry_after' => $retryAfter],
            429,
            array_merge($meta, ['retry_after' => $retryAfter])
        );
    }

    /**
     * 500 Internal Server Error
     */
    public static function serverError(string $message = 'Internal server error', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::INTERNAL_ERROR,
            $message,
            $details,
            500,
            $meta
        );
    }

    /**
     * 503 Service Unavailable
     */
    public static function serviceUnavailable(string $message = 'Service unavailable', mixed $details = null, array $meta = []): JsonResponse
    {
        return static::error(
            ErrorCode::SERVICE_UNAVAILABLE,
            $message,
            $details,
            503,
            $meta
        );
    }

    /**
     * Build metadata for response
     */
    protected static function buildMeta(array $additionalMeta = []): array
    {
        $meta = [
            'request_id' => static::getRequestId(),
            'timestamp' => now()->toIso8601String(),
            'version' => static::API_VERSION,
        ];

        // Add execution time if available
        if (defined('LARAVEL_START')) {
            $meta['execution_time'] = round((microtime(true) - LARAVEL_START) * 1000, 2).'ms';
        }

        return array_merge($meta, $additionalMeta);
    }

    /**
     * Get or generate request ID
     */
    protected static function getRequestId(): string
    {
        // Try to get from request header (if set by load balancer/proxy)
        $requestId = request()->header('X-Request-ID');

        if (! $requestId) {
            // Generate new request ID
            $requestId = Str::uuid()->toString();

            // Store in request for consistency
            request()->headers->set('X-Request-ID', $requestId);
        }

        return $requestId;
    }

    /**
     * Create response from Laravel Paginator
     */
    public static function fromPaginator(
        \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator,
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        return static::paginated(
            $paginator->items(),
            [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
            $message,
            $meta
        );
    }

    /**
     * Create response from exception
     *
     * @param  bool  $debug  Include debug information
     */
    public static function fromException(\Throwable $exception, bool $debug = false): JsonResponse
    {
        $code = ErrorCode::INTERNAL_ERROR;
        $message = 'An error occurred';
        $statusCode = 500;
        $details = null;

        // Map common exceptions
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $code = ErrorCode::VALIDATION_ERROR;
            $message = 'Validation failed';
            $statusCode = 422;
            $details = $exception->errors();
        } elseif ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            $code = ErrorCode::UNAUTHORIZED;
            $message = 'Unauthenticated';
            $statusCode = 401;
        } elseif ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $code = ErrorCode::FORBIDDEN;
            $message = 'Forbidden';
            $statusCode = 403;
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $code = ErrorCode::NOT_FOUND;
            $message = 'Resource not found';
            $statusCode = 404;
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
            $code = ErrorCode::RATE_LIMIT_EXCEEDED;
            $message = 'Too many requests';
            $statusCode = 429;
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: 'HTTP error';
        }

        // Add debug information in development
        if ($debug) {
            $details = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 0,
                        'function' => $trace['function'] ?? 'unknown',
                    ];
                })->take(5)->toArray(),
            ];
        }

        return static::error($code, $message, $details, $statusCode);
    }
}
