# Pipelines Test Summaries

Test documentation for data processing pipelines and batch operations.

---

## Components Covered

### Textract OCR Pipeline
**Namespace**: `app/Pipelines/Textract/`
**Job**: `app/Jobs/ProcessDrivePdfJob.php`
**Test Files**: `tests/Feature/TextractPipelineTest.php`

**Pipeline Steps**:

1. **DownloadDriveFileStep** - Fetch PDF from Google Drive
   - Google Drive API integration
   - Authentication with service account
   - Download to temporary storage
   - File validation (PDF format, size limits)

2. **UploadInputToS3Step** - Upload to AWS S3
   - S3 bucket configuration
   - Object key generation
   - Multipart upload for large files
   - ACL and encryption settings

3. **StartAnalysisStep** - Start AWS Textract job
   - Textract API call
   - Job configuration (features, output)
   - Job ID capture
   - Error handling

4. **WaitAndFetchStep** - Poll for completion
   - Exponential backoff polling
   - Timeout handling (max 30 minutes)
   - Status checking (SUCCEEDED, FAILED, IN_PROGRESS)
   - Result retrieval

5. **CollectLinesStep** - Extract LINE blocks
   - Parse Textract JSON response
   - Filter LINE blocks
   - Extract text content
   - Preserve bounding box coordinates
   - Maintain page numbers

6. **ReconstructPdfStep** - Overlay invisible text
   - Load original PDF with FPDI
   - Create invisible text layer with TCPDF
   - Position text using bounding boxes
   - Maintain visual appearance
   - Create searchable PDF

7. **SaveResultsStep** - Store to S3
   - Save Textract JSON to S3
   - Save searchable PDF to S3
   - Update database records
   - Generate access URLs

**Queue**: `textract`

**Configuration**: `config/textract.php`

**Key Capabilities**:
- Batch PDF processing
- Parallel job execution
- Progress tracking
- Error recovery
- Cost optimization (page limits)

---

### Odluke Ingestion Pipeline
**Service**: `app/Services/Odluke/OdlukeIngestService.php`
**Client**: `app/Services/Odluke/OdlukeClient.php`
**Test Files**: `tests/Feature/OdlukeIngestionTest.php`

**Pipeline Steps**:

1. **Fetch Decision** - Get from odluke.sudovi.hr
   - HTTP client with circuit breaker
   - Connection pooling
   - Exponential backoff
   - Rate limiting (30 RPM)

2. **Parse HTML** - Extract structured data
   - Court name and location
   - Case number
   - Decision date
   - Judge information
   - Decision text content
   - Legal citations

3. **Generate Embeddings** - Create vector embeddings
   - OpenAI text-embedding-3-small
   - Chunk long decisions (max 8191 tokens)
   - Batch embedding generation
   - Dimension: 1536

4. **Store in Vector DB** - Save to PostgreSQL
   - Insert into court_decisions table
   - Store embeddings in pgvector column
   - Save metadata (court, date, case number)
   - Index for similarity search

5. **Sync to Neo4j** - Update graph database
   - Create Decision node
   - Extract and create Law nodes (citations)
   - Create CITES relationships
   - Create SIMILAR_TO relationships (vector similarity)
   - Update graph indexes

**Circuit Breaker Configuration**:
- Failure threshold: 3 failures
- Timeout: 60 seconds
- Half-open retry: Automatic after timeout

**Rate Limiting**:
- Max requests per minute: 30 (configurable via `ODLUKE_RPM`)
- Delay between requests: 700ms (configurable via `ODLUKE_DELAY_MS`)
- Backoff on errors: 800ms (configurable via `ODLUKE_BACKOFF_MS`)

---

### Law Ingestion Pipeline
**Service**: `app/Services/LawIngestionService.php`
**Test Files**: `tests/Feature/LawIngestionTest.php`

**Pipeline Steps**:

1. **Parse Law Text** - Extract articles
   - Regex-based article extraction
   - Article numbering (Članak 1, 2, 3...)
   - Section identification
   - Paragraph splitting

2. **Generate Embeddings** - Create vectors
   - Per-article embeddings
   - Context window (article + surrounding articles)
   - OpenAI text-embedding-3-small

3. **Store in Vector DB** - Save to PostgreSQL
   - Insert into laws table
   - Article-level granularity
   - Law metadata (name, effective date, version)

4. **Sync to Neo4j** - Update graph
   - Create Law node per article
   - Create HAS_SECTION relationships
   - Create AMENDS relationships (for law updates)
   - Create REFERENCES relationships (cross-law citations)

**Supported Laws**:
- ZKP (Zakon o kaznenom postupku) - Criminal Procedure Act
- Kazneni zakon (KZ) - Criminal Code
- Ustav RH - Croatian Constitution
- Zakon o Državnom odvjetništvu - State Attorney Act

---

### Case Document Ingestion Pipeline
**Service**: `app/Services/CaseDocumentIngestionService.php`

**Pipeline Steps**:

1. **Extract Text** - Get document content
   - PDF text extraction (Textract results)
   - DOCX text extraction
   - Plain text files

2. **Chunk Documents** - Split into chunks
   - Semantic chunking (sentence boundaries)
   - Max chunk size: 512 tokens
   - Overlap: 50 tokens
   - Preserve context

3. **Generate Embeddings** - Create vectors
   - Per-chunk embeddings
   - Batch processing
   - OpenAI text-embedding-3-small

4. **Store in Vector DB** - Save to PostgreSQL
   - Insert into case_documents table
   - Chunk-level storage
   - Case relationship (foreign key)
   - Document type metadata

---

## Test Coverage Areas

### Unit Tests

**Step Execution**:
- ✅ Each step runs independently
- ✅ Input validation
- ✅ Output validation
- ✅ Error handling per step

**Data Transformation**:
- ✅ PDF → Text extraction
- ✅ HTML → Structured data parsing
- ✅ Text → Embeddings generation
- ✅ Embeddings → Database storage

---

### Integration Tests

**Full Pipeline Execution**:
- ✅ End-to-end Textract pipeline (Drive → S3 → Textract → PDF)
- ✅ End-to-end Odluke ingestion (Fetch → Parse → Embed → Store → Graph)
- ✅ End-to-end Law ingestion (Parse → Embed → Store → Graph)

**Error Recovery**:
- ✅ AWS Textract failures (retry logic)
- ✅ OpenAI API failures (fallback, retry)
- ✅ Database connection errors (transaction rollback)
- ✅ Network timeouts (exponential backoff)

**Performance**:
- ✅ Large PDF processing (100+ pages)
- ✅ Batch decision ingestion (100+ decisions)
- ✅ Parallel job processing (queue workers)

**External Service Integration**:
- ✅ Google Drive API
- ✅ AWS S3
- ✅ AWS Textract
- ✅ OpenAI API
- ✅ Neo4j database
- ✅ PostgreSQL database

---

## Running Tests

### All Pipeline Tests
```bash
./scripts/run-tests.sh --filter=PipelineTest
```

### Specific Pipelines
```bash
# Textract pipeline
./scripts/run-tests.sh --filter=TextractPipelineTest

# Odluke ingestion
./scripts/run-tests.sh --filter=OdlukeIngestionTest

# Law ingestion
./scripts/run-tests.sh --filter=LawIngestionTest

# Case document ingestion
./scripts/run-tests.sh --filter=CaseDocumentIngestionTest
```

### Manual Pipeline Testing
```bash
# Process single PDF from Google Drive
php artisan textract:process-drive-folder FOLDER_ID --limit=1

# Ingest court decisions
php artisan odluke:ingest-by-ids dec_123,dec_456

# Check Textract job status
php artisan textract:check-status JOB_ID

# Monitor queue workers
php artisan queue:work --queue=textract,agents,default --tries=1
```

---

## Test Summaries

Detailed test summaries for pipelines and pipeline steps:

**Textract OCR Pipeline**:
- ✅ [Textract Pipeline Integration](textract-pipeline-integration.md) - Full end-to-end pipeline test
- ✅ [Start Analysis Step](start-analysis-step.md) - Textract job initiation
- ✅ [Wait and Fetch](wait-and-fetch.md) - Textract job polling and result retrieval
- ✅ [Analyze Textract Layout](analyze-textract-layout.md) - Layout analysis step
- ✅ [Extract Document Metadata](extract-document-metadata.md) - Metadata extraction step
- ✅ [Reconstruct PDF v2](reconstruct-pdf-v2.md) - PDF reconstruction with invisible text overlay
- ✅ [Save Analysis Results](save-analysis-results.md) - Result storage step
- ✅ [Upload Output to S3](upload-output-to-s3.md) - S3 upload step

**Other Pipelines**:
- [ ] Odluke ingestion pipeline (to be added)
- [ ] Law ingestion pipeline (to be added)
- [ ] Case document ingestion pipeline (to be added)

---

## Known Issues

*(Document any known issues, flaky tests, or technical debt here)*

**Textract Rate Limits**:
- AWS Textract has page/minute limits
- Tests use --limit flag to avoid hitting limits
- Integration tests may be slow due to polling

**Odluke Circuit Breaker**:
- Circuit breaker may open during tests if odluke.sudovi.hr is slow
- Tests include circuit breaker state reset
- Rate limiting may cause test delays

---

**Last Updated**: 2025-11-09
