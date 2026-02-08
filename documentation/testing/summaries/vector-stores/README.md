# Vector Stores Test Summaries

Test documentation for vector similarity search and embedding services.

---

## Components Covered

### LawVectorStoreService
**File**: `app/Services/LawVectorStoreService.php`
**Test File**: `tests/Unit/Services/LawVectorStoreServiceTest.php`

Vector storage and search for Croatian laws corpus:
- ZKP (Zakon o kaznenom postupku) - Criminal Procedure Act
- Kazneni zakon (KZ) - Criminal Code
- Ustav RH - Croatian Constitution

**Key Capabilities**:
- Law article embedding generation
- Semantic search with pgvector
- Law citation extraction
- Keyword-based filtering
- Hybrid search (vector + keyword)

---

### CourtDecisionVectorStoreService
**File**: `app/Services/CourtDecisionVectorStoreService.php`
**Test File**: `tests/Unit/Services/CourtDecisionVectorStoreServiceTest.php`

Vector storage and search for court decisions from odluke.sudovi.hr:
- Decision text embeddings
- Court metadata (Županijski sud, Vrhovni sud, etc.)
- Decision date and case number
- Judge information
- Legal area categorization

**Key Capabilities**:
- Decision ingestion from odluke.sudovi.hr
- Semantic search across decisions
- Court and date filtering
- Citation network analysis
- Relevance scoring

---

### CaseVectorStoreService
**File**: `app/Services/CaseVectorStoreService.php`
**Test File**: `tests/Unit/Services/CaseVectorStoreServiceTest.php`

Vector storage and search for internal case documents:
- Case submissions (podnesci)
- Case files and evidence
- Attorney notes
- Client communications

**Key Capabilities**:
- Document chunk embeddings
- Case-scoped search
- Document type filtering
- Temporal search (by date range)
- Multi-document retrieval

---

### TextractVectorStoreService
**File**: `app/Services/TextractVectorStoreService.php`
**Test File**: `tests/Unit/Services/TextractVectorStoreServiceTest.php`

Vector storage and search for OCR'd PDF documents:
- Textract-extracted text
- Page-level embeddings
- Bounding box metadata
- Confidence scores

**Key Capabilities**:
- OCR text ingestion
- Page-aware search
- Confidence filtering
- Bounding box retrieval for highlighting

---

## Test Coverage Areas

### Unit Tests

**Embedding Generation**:
- ✅ Generate embeddings for text chunks
- ✅ Handle long text (chunking)
- ✅ Handle empty/null text
- ✅ Validate embedding dimensions (1536 for text-embedding-3-small)

**Vector Storage**:
- ✅ Insert embeddings into PostgreSQL pgvector
- ✅ Update existing embeddings
- ✅ Delete embeddings
- ✅ Batch insertion

**Similarity Search**:
- ✅ Cosine similarity calculations
- ✅ Top-k retrieval
- ✅ Similarity threshold filtering
- ✅ Metadata filtering

**Hybrid Search**:
- ✅ Vector + keyword combination
- ✅ Reciprocal Rank Fusion (RRF)
- ✅ Weighted scoring

---

### Integration Tests

**Full Pipeline**:
- ✅ Ingest → Embed → Store → Search
- ✅ Multi-corpus search
- ✅ Cross-corpus deduplication

**Performance**:
- ✅ Search latency < 500ms
- ✅ Batch processing throughput
- ✅ Index query optimization

**Error Handling**:
- ✅ OpenAI API failures
- ✅ Database connection errors
- ✅ Invalid vector dimensions
- ✅ Malformed metadata

---

## Running Tests

### All Vector Store Tests
```bash
./scripts/run-tests.sh --filter=VectorStore
```

### Specific Service Tests
```bash
# Law vector store
./scripts/run-tests.sh --filter=LawVectorStoreServiceTest

# Court decision vector store
./scripts/run-tests.sh --filter=CourtDecisionVectorStoreServiceTest

# Case vector store
./scripts/run-tests.sh --filter=CaseVectorStoreServiceTest

# Textract vector store
./scripts/run-tests.sh --filter=TextractVectorStoreServiceTest
```

---

## Test Summaries

Detailed test summaries for each vector store service:

- ✅ [Law Vector Store](law.md) - Croatian laws corpus (ZKP, KZ, Ustav RH)
- ✅ [Court Decision Vector Store](court-decision.md) - Court decisions from odluke.sudovi.hr
- ✅ [Case Vector Store](case.md) - Internal case documents and submissions
- ✅ [Textract Vector Store](textract.md) - OCR'd PDF documents with bounding boxes

---

## Known Issues

*(Document any known issues, flaky tests, or technical debt here)*

---

**Last Updated**: 2025-11-09
