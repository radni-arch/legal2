<?php

namespace App\Exceptions;

use Exception;

class PdfException extends Exception
{
    public const PDF_NOT_FOUND = 9001;

    public const PAGE_COUNT_FAILED = 9002;

    public const TEXT_EXTRACTION_FAILED = 9003;

    public const ARTICLE_DETECTION_FAILED = 9004;

    public const PAGE_EXPORT_FAILED = 9005;

    public const FILE_WRITE_FAILED = 9006;

    public const PROCESS_EXECUTION_FAILED = 9007;

    public const INVALID_PDF = 9008;

    public const RENDER_FAILED = 9009;

    public const UNEXPECTED_ERROR = 9999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('PDF exception occurred', [
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
            self::PDF_NOT_FOUND => 'PDF file not found.',
            self::PAGE_COUNT_FAILED => 'Failed to determine PDF page count.',
            self::TEXT_EXTRACTION_FAILED => 'Failed to extract text from PDF.',
            self::ARTICLE_DETECTION_FAILED => 'Failed to detect articles in PDF.',
            self::PAGE_EXPORT_FAILED => 'Failed to export PDF pages.',
            self::FILE_WRITE_FAILED => 'Failed to write output file.',
            self::PROCESS_EXECUTION_FAILED => 'External tool execution failed.',
            self::INVALID_PDF => 'Invalid or corrupted PDF file.',
            self::RENDER_FAILED => 'Failed to render PDF article.',
            default => 'PDF processing failed.',
        };
    }
}
