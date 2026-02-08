<?php

namespace App\Exceptions;

use Exception;

class IngestException extends Exception
{
    public const FILE_READ_FAILED = 5001;

    public const FILE_PARSING_FAILED = 5002;

    public const CHUNKING_FAILED = 5003;

    public const EMBEDDING_FAILED = 5004;

    public const STORAGE_FAILED = 5005;

    public const INVALID_FILE_FORMAT = 5006;

    public const FILE_TOO_LARGE = 5007;

    public const METADATA_EXTRACTION_FAILED = 5008;

    public const PIPELINE_FAILED = 5009;

    public const HTTP_FETCH_FAILED = 5010;

    public const PDF_RENDER_FAILED = 5011;

    public const PDF_MERGE_FAILED = 5012;

    public const ARTICLE_PARSING_FAILED = 5013;

    public const VECTOR_STORE_FAILED = 5014;

    public const LAW_CREATION_FAILED = 5015;

    public const QUALITY_CHECK_FAILED = 5016;

    public const NORMALIZATION_FAILED = 5017;

    public const CHUNK_GENERATION_FAILED = 5018;

    public const NO_CONTENT_ERROR = 5019;

    public const OCR_QUALITY_BELOW_THRESHOLD = 5020;

    public const UNEXPECTED_ERROR = 5999;

    /**
     * Report the exception
     */
    public function report(): bool
    {
        logger()->error('Ingestion exception occurred', [
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
            self::FILE_READ_FAILED => 'Failed to read file for ingestion.',
            self::FILE_PARSING_FAILED => 'Failed to parse file content.',
            self::CHUNKING_FAILED => 'Failed to chunk document for processing.',
            self::EMBEDDING_FAILED => 'Failed to generate embeddings for document.',
            self::STORAGE_FAILED => 'Failed to store ingested data.',
            self::INVALID_FILE_FORMAT => 'Unsupported file format.',
            self::FILE_TOO_LARGE => 'File exceeds maximum size limit.',
            self::METADATA_EXTRACTION_FAILED => 'Failed to extract document metadata.',
            self::PIPELINE_FAILED => 'Ingestion pipeline failed.',
            self::HTTP_FETCH_FAILED => 'Failed to fetch document from URL.',
            self::PDF_RENDER_FAILED => 'Failed to render PDF document.',
            self::PDF_MERGE_FAILED => 'Failed to merge PDF documents.',
            self::ARTICLE_PARSING_FAILED => 'Failed to parse law articles.',
            self::VECTOR_STORE_FAILED => 'Failed to store document in vector database.',
            self::LAW_CREATION_FAILED => 'Failed to create law record.',
            self::QUALITY_CHECK_FAILED => 'Failed to perform OCR quality check.',
            self::NORMALIZATION_FAILED => 'Failed to normalize document text.',
            self::CHUNK_GENERATION_FAILED => 'Failed to generate text chunks.',
            self::NO_CONTENT_ERROR => 'No content available for ingestion.',
            self::OCR_QUALITY_BELOW_THRESHOLD => 'OCR quality is below acceptable threshold.',
            default => 'Document ingestion failed.',
        };
    }
}
