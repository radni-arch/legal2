<?php

namespace App\Http\Responses;

/**
 * Standardized API Error Codes
 *
 * Provides consistent error code constants used across all API responses.
 * These codes help clients programmatically identify and handle specific error scenarios.
 */
class ErrorCode
{
    // ========================================
    // Client Errors (4xx)
    // ========================================

    /**
     * Validation Error (400)
     * Request data failed validation rules
     */
    const VALIDATION_ERROR = 'VALIDATION_ERROR';

    /**
     * Unauthorized (401)
     * Authentication required or authentication failed
     */
    const UNAUTHORIZED = 'UNAUTHORIZED';

    /**
     * Forbidden (403)
     * Authenticated but lacks required permissions
     */
    const FORBIDDEN = 'FORBIDDEN';

    /**
     * Not Found (404)
     * Requested resource does not exist
     */
    const NOT_FOUND = 'NOT_FOUND';

    /**
     * Conflict (409)
     * Request conflicts with current resource state
     */
    const CONFLICT = 'CONFLICT';

    /**
     * Unprocessable Entity (422)
     * Request is well-formed but semantically incorrect
     */
    const UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';

    /**
     * Rate Limit Exceeded (429)
     * Too many requests in given time period
     */
    const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';

    /**
     * Bad Request (400)
     * Generic client error for malformed requests
     */
    const BAD_REQUEST = 'BAD_REQUEST';

    /**
     * Method Not Allowed (405)
     * HTTP method not supported for endpoint
     */
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';

    /**
     * Invalid Input (400)
     * Input data is invalid or malformed
     */
    const INVALID_INPUT = 'INVALID_INPUT';

    // ========================================
    // Server Errors (5xx)
    // ========================================

    /**
     * Internal Server Error (500)
     * Generic server error
     */
    const INTERNAL_ERROR = 'INTERNAL_ERROR';

    /**
     * Service Unavailable (503)
     * Service temporarily unavailable
     */
    const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    /**
     * Database Error (500)
     * Database operation failed
     */
    const DATABASE_ERROR = 'DATABASE_ERROR';

    /**
     * External Service Error (502/503)
     * External API or service failed
     */
    const EXTERNAL_SERVICE_ERROR = 'EXTERNAL_SERVICE_ERROR';

    // ========================================
    // OpenAI Integration Errors
    // ========================================

    /**
     * OpenAI API Error
     * OpenAI API request failed
     */
    const OPENAI_API_ERROR = 'OPENAI_API_ERROR';

    /**
     * OpenAI Rate Limit
     * OpenAI rate limit exceeded
     */
    const OPENAI_RATE_LIMIT = 'OPENAI_RATE_LIMIT';

    /**
     * OpenAI Invalid Request
     * Invalid request to OpenAI API
     */
    const OPENAI_INVALID_REQUEST = 'OPENAI_INVALID_REQUEST';

    /**
     * OpenAI Timeout
     * OpenAI API request timed out
     */
    const OPENAI_TIMEOUT = 'OPENAI_TIMEOUT';

    // ========================================
    // Vector Search Errors
    // ========================================

    /**
     * Search Error
     * Vector or hybrid search operation failed
     */
    const SEARCH_ERROR = 'SEARCH_ERROR';

    /**
     * Invalid Query
     * Search query is invalid or malformed
     */
    const INVALID_QUERY = 'INVALID_QUERY';

    /**
     * No Results Found
     * Search returned no results
     */
    const NO_RESULTS_FOUND = 'NO_RESULTS_FOUND';

    // ========================================
    // Document Ingestion Errors
    // ========================================

    /**
     * Ingestion Failed
     * Document ingestion process failed
     */
    const INGESTION_FAILED = 'INGESTION_FAILED';

    /**
     * Invalid Document Format
     * Document format not supported
     */
    const INVALID_DOCUMENT_FORMAT = 'INVALID_DOCUMENT_FORMAT';

    /**
     * Document Too Large
     * Document exceeds size limits
     */
    const DOCUMENT_TOO_LARGE = 'DOCUMENT_TOO_LARGE';

    /**
     * Parsing Error
     * Failed to parse document content
     */
    const PARSING_ERROR = 'PARSING_ERROR';

    // ========================================
    // File Upload Errors
    // ========================================

    /**
     * Upload Failed
     * File upload operation failed
     */
    const UPLOAD_FAILED = 'UPLOAD_FAILED';

    /**
     * Invalid File Type
     * File type not allowed
     */
    const INVALID_FILE_TYPE = 'INVALID_FILE_TYPE';

    /**
     * File Too Large
     * File exceeds maximum size
     */
    const FILE_TOO_LARGE = 'FILE_TOO_LARGE';

    /**
     * Storage Error
     * Failed to store file
     */
    const STORAGE_ERROR = 'STORAGE_ERROR';

    // ========================================
    // MCP Protocol Errors
    // ========================================

    /**
     * MCP Authentication Failed
     * MCP token authentication failed
     */
    const MCP_AUTH_FAILED = 'MCP_AUTH_FAILED';

    /**
     * MCP Tool Not Found
     * Requested MCP tool does not exist
     */
    const MCP_TOOL_NOT_FOUND = 'MCP_TOOL_NOT_FOUND';

    /**
     * MCP Execution Failed
     * MCP tool execution failed
     */
    const MCP_EXECUTION_FAILED = 'MCP_EXECUTION_FAILED';

    /**
     * MCP Invalid Parameters
     * MCP tool parameters are invalid
     */
    const MCP_INVALID_PARAMETERS = 'MCP_INVALID_PARAMETERS';

    // ========================================
    // Agent System Errors
    // ========================================

    /**
     * Agent Not Found
     * Requested agent does not exist
     */
    const AGENT_NOT_FOUND = 'AGENT_NOT_FOUND';

    /**
     * Agent Execution Failed
     * Agent execution failed
     */
    const AGENT_EXECUTION_FAILED = 'AGENT_EXECUTION_FAILED';

    /**
     * Agent Timeout
     * Agent execution timed out
     */
    const AGENT_TIMEOUT = 'AGENT_TIMEOUT';

    /**
     * Collaboration Failed
     * Agent collaboration operation failed
     */
    const COLLABORATION_FAILED = 'COLLABORATION_FAILED';

    // ========================================
    // Legal Domain Errors
    // ========================================

    /**
     * Case Not Found
     * Legal case does not exist
     */
    const CASE_NOT_FOUND = 'CASE_NOT_FOUND';

    /**
     * Invalid Law Code
     * Invalid or unsupported Croatian law code
     */
    const INVALID_LAW_CODE = 'INVALID_LAW_CODE';

    /**
     * Misconduct Analysis Failed
     * Prosecutorial misconduct analysis failed
     */
    const MISCONDUCT_ANALYSIS_FAILED = 'MISCONDUCT_ANALYSIS_FAILED';

    /**
     * Legal Reasoning Failed
     * Legal reasoning operation failed
     */
    const LEGAL_REASONING_FAILED = 'LEGAL_REASONING_FAILED';

    /**
     * Document Generation Failed
     * Legal document generation failed
     */
    const DOCUMENT_GENERATION_FAILED = 'DOCUMENT_GENERATION_FAILED';

    // ========================================
    // Graph Database Errors
    // ========================================

    /**
     * Neo4j Connection Failed
     * Cannot connect to Neo4j database
     */
    const NEO4J_CONNECTION_FAILED = 'NEO4J_CONNECTION_FAILED';

    /**
     * Graph Query Failed
     * Neo4j query execution failed
     */
    const GRAPH_QUERY_FAILED = 'GRAPH_QUERY_FAILED';

    /**
     * Graph Visualization Failed
     * Graph visualization generation failed
     */
    const GRAPH_VISUALIZATION_FAILED = 'GRAPH_VISUALIZATION_FAILED';

    // ========================================
    // Analytics & Prediction Errors
    // ========================================

    /**
     * Analytics Failed
     * Statistical analysis operation failed
     */
    const ANALYTICS_FAILED = 'ANALYTICS_FAILED';

    /**
     * Prediction Failed
     * Predictive analytics operation failed
     */
    const PREDICTION_FAILED = 'PREDICTION_FAILED';

    /**
     * Insufficient Data
     * Not enough data for analysis
     */
    const INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';

    // ========================================
    // Monitoring & Performance Errors
    // ========================================

    /**
     * Monitoring Unavailable
     * Monitoring data not available
     */
    const MONITORING_UNAVAILABLE = 'MONITORING_UNAVAILABLE';

    /**
     * Performance Degraded
     * System performance is degraded
     */
    const PERFORMANCE_DEGRADED = 'PERFORMANCE_DEGRADED';

    // ========================================
    // Security Errors
    // ========================================

    /**
     * Honeypot Triggered
     * Security honeypot endpoint triggered
     */
    const HONEYPOT_TRIGGERED = 'HONEYPOT_TRIGGERED';

    /**
     * Suspicious Activity
     * Suspicious activity detected
     */
    const SUSPICIOUS_ACTIVITY = 'SUSPICIOUS_ACTIVITY';

    /**
     * Token Invalid
     * API token is invalid or expired
     */
    const TOKEN_INVALID = 'TOKEN_INVALID';

    /**
     * Token Expired
     * API token has expired
     */
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';

    /**
     * Get human-readable description for error code
     *
     * @param  string  $code  Error code constant
     * @return string Human-readable description
     */
    public static function getDescription(string $code): string
    {
        $descriptions = [
            self::VALIDATION_ERROR => 'The request data failed validation',
            self::UNAUTHORIZED => 'Authentication is required',
            self::FORBIDDEN => 'You do not have permission to access this resource',
            self::NOT_FOUND => 'The requested resource was not found',
            self::CONFLICT => 'The request conflicts with the current state',
            self::UNPROCESSABLE_ENTITY => 'The request is semantically incorrect',
            self::RATE_LIMIT_EXCEEDED => 'Too many requests, please slow down',
            self::BAD_REQUEST => 'The request is malformed',
            self::INTERNAL_ERROR => 'An internal server error occurred',
            self::SERVICE_UNAVAILABLE => 'The service is temporarily unavailable',
            self::OPENAI_API_ERROR => 'OpenAI API request failed',
            self::SEARCH_ERROR => 'Search operation failed',
            self::INGESTION_FAILED => 'Document ingestion failed',
            self::UPLOAD_FAILED => 'File upload failed',
            self::MCP_AUTH_FAILED => 'MCP authentication failed',
            self::AGENT_NOT_FOUND => 'The requested agent was not found',
            self::CASE_NOT_FOUND => 'The legal case was not found',
            self::NEO4J_CONNECTION_FAILED => 'Cannot connect to graph database',
            self::HONEYPOT_TRIGGERED => 'Security honeypot triggered',
        ];

        return $descriptions[$code] ?? 'An error occurred';
    }

    /**
     * Check if error code is a client error (4xx)
     *
     * @param  string  $code  Error code constant
     * @return bool True if client error
     */
    public static function isClientError(string $code): bool
    {
        $clientErrors = [
            self::VALIDATION_ERROR,
            self::UNAUTHORIZED,
            self::FORBIDDEN,
            self::NOT_FOUND,
            self::CONFLICT,
            self::UNPROCESSABLE_ENTITY,
            self::RATE_LIMIT_EXCEEDED,
            self::BAD_REQUEST,
            self::METHOD_NOT_ALLOWED,
            self::INVALID_INPUT,
            self::INVALID_QUERY,
            self::INVALID_DOCUMENT_FORMAT,
            self::INVALID_FILE_TYPE,
            self::FILE_TOO_LARGE,
            self::DOCUMENT_TOO_LARGE,
            self::MCP_INVALID_PARAMETERS,
            self::INVALID_LAW_CODE,
            self::TOKEN_INVALID,
            self::TOKEN_EXPIRED,
        ];

        return in_array($code, $clientErrors);
    }

    /**
     * Check if error code is a server error (5xx)
     *
     * @param  string  $code  Error code constant
     * @return bool True if server error
     */
    public static function isServerError(string $code): bool
    {
        return ! self::isClientError($code);
    }
}
