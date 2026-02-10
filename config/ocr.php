<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OCR Engine Selection
    |--------------------------------------------------------------------------
    | 'auto' uses DocumentOcrRouter weighted scoring to choose.
    | 'tesseract' or 'textract' forces a specific engine.
    */
    'default_engine' => env('OCR_DEFAULT_ENGINE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Routing Weights (DocumentOcrRouter)
    |--------------------------------------------------------------------------
    */
    'routing' => [
        'croatian_text_threshold' => (float) env('OCR_CROATIAN_THRESHOLD', 0.6),
        'structured_content_threshold' => (float) env('OCR_STRUCTURED_THRESHOLD', 0.3),
        'textract_confidence_threshold' => (float) env('OCR_TEXTRACT_MIN_CONFIDENCE', 0.85),
        'fallback_confidence_threshold' => (float) env('OCR_FALLBACK_THRESHOLD', 0.70),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Comparison (OcrQualityComparator)
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'min_confidence' => (float) env('OCR_MIN_CONFIDENCE', 0.82),
        'min_coverage' => (float) env('OCR_MIN_COVERAGE', 0.75),
        'max_low_confidence_pages' => (int) env('OCR_MAX_LOW_CONF_PAGES', 3),
        'min_improvement_percent' => (float) env('OCR_MIN_IMPROVEMENT', 5.0),
        'skip_embedding_on_low_quality' => (bool) env('OCR_SKIP_EMBEDDING_ON_LOW_QUALITY', false),
        'croatian_diacritics' => ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'],
    ],

    /*
    |--------------------------------------------------------------------------
    | ocrmypdf Configuration (primary local OCR engine)
    |--------------------------------------------------------------------------
    | Wraps Tesseract + Ghostscript with deskew/denoise in a single CLI call.
    | Install: pip install ocrmypdf
    | Requires: tesseract-ocr, tesseract-ocr-hrv, ghostscript
    */
    'ocrmypdf' => [
        'binary' => env('OCRMYPDF_BINARY', 'ocrmypdf'),
        'languages' => env('OCRMYPDF_LANGUAGES', 'hrv+eng'),
        'timeout' => (int) env('OCRMYPDF_TIMEOUT', 600),
        'max_pages' => (int) env('OCRMYPDF_MAX_PAGES', 0),
        'deskew' => (bool) env('OCRMYPDF_DESKEW', true),
        'clean' => (bool) env('OCRMYPDF_CLEAN', false),
        'remove_background' => (bool) env('OCRMYPDF_REMOVE_BG', false),
        'jobs' => (int) env('OCRMYPDF_JOBS', 2),
        'pdf_renderer' => env('OCRMYPDF_RENDERER', 'sandwich'),
        'output_type' => env('OCRMYPDF_OUTPUT_TYPE', 'pdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tesseract Configuration (legacy/fallback local OCR engine)
    |--------------------------------------------------------------------------
    */
    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', '/usr/bin/tesseract'),
        'convert_binary' => env('IMAGEMAGICK_CONVERT', '/usr/bin/convert'),
        'languages' => env('TESSERACT_LANGUAGES', 'hrv+eng'),
        'dpi' => (int) env('TESSERACT_DPI', 300),
        'psm' => (int) env('TESSERACT_PSM', 3),
        'oem' => (int) env('TESSERACT_OEM', 1),
        'page_timeout' => (int) env('TESSERACT_PAGE_TIMEOUT', 120),
        'max_pages' => (int) env('TESSERACT_MAX_PAGES', 0),
        'preserve_layout' => (bool) env('TESSERACT_PRESERVE_LAYOUT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Existing Text Detection (CheckExistingTextStep)
    |--------------------------------------------------------------------------
    | Minimum words per page required to skip AWS Textract and route to
    | OcrmypdfService with --skip-text instead. Saves ~$1.50/1000 pages.
    */
    'min_words_per_page_skip' => (int) env('OCR_MIN_WORDS_PER_PAGE_SKIP', 50),

    /*
    |--------------------------------------------------------------------------
    | Re-OCR on Quality Failure
    |--------------------------------------------------------------------------
    | When CheckOcrQualityStep flags a document for review, force re-OCR
    | through the Tesseract/ocrmypdf path regardless of routing score.
    */
    'force_reocr_on_review' => (bool) env('OCR_FORCE_REOCR_ON_REVIEW', true),

    /*
    |--------------------------------------------------------------------------
    | Chunking Configuration (PersistReconstructedStep)
    |--------------------------------------------------------------------------
    | Controls text chunking for embedding after OCR extraction.
    */
    'chunking' => [
        'chunk_size' => (int) env('OCR_CHUNK_SIZE', 1200),
        'overlap' => (int) env('OCR_CHUNK_OVERLAP', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration (PersistReconstructedStep)
    |--------------------------------------------------------------------------
    | Model and provider used for embedding OCR-extracted text.
    */
    'embedding' => [
        'model' => env('OCR_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'provider' => env('OCR_EMBEDDING_PROVIDER', 'openai'),
    ],
];
