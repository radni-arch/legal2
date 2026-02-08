<?php

namespace App\Exceptions;

use Exception;

class TextractException extends Exception
{
    public const S3_UPLOAD_FAILED = 8001;

    public const TEXTRACT_START_FAILED = 8002;

    public const TEXTRACT_TIMEOUT = 8003;

    public const TEXTRACT_JOB_FAILED = 8004;

    public const RESULT_RETRIEVAL_FAILED = 8005;

    public const LINE_COLLECTION_FAILED = 8006;

    public const PDF_RECONSTRUCTION_FAILED = 8007;

    public const INVALID_FILE_FORMAT = 8008;

    public const FILE_TOO_LARGE = 8009;

    public const JOB_NOT_FOUND = 8010;

    public const NO_CONTENT = 8011;

    public const UNEXPECTED_ERROR = 8999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Textract exception occurred', [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);

        return true;
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return match ($this->getCode()) {
            self::S3_UPLOAD_FAILED => 'Failed to upload file to S3.',
            self::TEXTRACT_START_FAILED => 'Failed to start OCR processing.',
            self::TEXTRACT_TIMEOUT => 'OCR processing timed out.',
            self::TEXTRACT_JOB_FAILED => 'OCR processing failed.',
            self::RESULT_RETRIEVAL_FAILED => 'Failed to retrieve OCR results.',
            self::LINE_COLLECTION_FAILED => 'Failed to collect OCR text lines.',
            self::PDF_RECONSTRUCTION_FAILED => 'Failed to create searchable PDF.',
            self::INVALID_FILE_FORMAT => 'Unsupported file format for OCR.',
            self::FILE_TOO_LARGE => 'File too large for OCR processing.',
            self::JOB_NOT_FOUND => 'Textract job not found.',
            self::NO_CONTENT => 'No content available for processing.',
            default => 'OCR processing failed.',
        };
    }
}
