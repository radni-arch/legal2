# Legal Fact Extraction for Court Decisions

This document describes the comprehensive legal fact extraction system for court decisions, which complements the citation extraction and similarity detection features.

## Overview

The Fact Extraction system extracts structured legal facts from Croatian court decisions using a hybrid approach:

1. **Pattern-Based Extraction**: Fast, reliable extraction of structured data (parties, dates, case metadata)
2. **LLM-Based Extraction**: Intelligent extraction of complex legal facts (issues, holdings, arguments, evidence)
3. **Fact Comparison**: Compare facts between decisions to identify similarities

## Features

### 1. Comprehensive Fact Extraction

Extract a complete set of legal facts from any court decision:

#### **Basic Facts** (Pattern-Based)
- **Case Metadata**: Case number, court, jurisdiction, decision type, ECLI, finality
- **Parties**: Plaintiffs, defendants, judges, other parties
- **Dates**: Decision date, publication date, filing date, hearing dates
- **Procedural Posture**: First instance, appeal, revision, prior proceedings

#### **Complex Facts** (LLM-Based)
- **Legal Issues**: Main legal questions presented to the court
- **Holding**: Court's ultimate conclusion or ruling
- **Key Findings**: Important factual findings by the court
- **Legal Grounds**: Legal provisions or principles cited as basis
- **Arguments**:
  - Plaintiff/Appellant arguments
  - Defendant/Respondent arguments
- **Relief Sought**: What the parties requested
- **Relief Granted**: What the court awarded
- **Standard of Review**: Standard applied (for appeals)
- **Key Evidence**: Important evidence mentioned
- **Dissent**: Whether there was a dissenting opinion
- **Summary**: Brief 2-3 sentence case summary

### 2. Fact Comparison

Compare extracted facts between two decisions to identify:
- Shared parties
- Common legal issues
- Similar legal grounds
- Similar relief sought/granted
- Procedural similarities

### 3. Batch Processing

Extract facts from multiple decisions in a single operation with error handling and reporting.

---

## API Reference

### MCP Tools

#### 1. `decision.extract_facts`

Extract all legal facts from a court decision.

**Input:**
```json
{
  "decision_id": "01HF123ABC...",
  "use_llm": true,
  "use_cache": true
}
```

**Parameters:**
- `decision_id` (required): Court decision document ID (ULID)
- `use_llm` (optional): Use LLM for complex fact extraction (default: true)
- `use_cache` (optional): Enable caching (default: true)

**Output:**
```json
{
  "success": true,
  "decision_id": "01HF123ABC...",
  "basic_facts": {
    "case_metadata": {
      "case_number": "Rev 123/2020",
      "court": "Vrhovni sud Republike Hrvatske",
      "jurisdiction": "HR",
      "decision_type": "Presuda",
      "ecli": "ECLI:HR:VSRH:2020:123",
      "finality": "final"
    },
    "parties": {
      "judge": "Dr. Ivan Horvat",
      "plaintiffs": ["ABC d.o.o.", "Marko Marić"],
      "defendants": ["XYZ d.o.o."],
      "other_parties": []
    },
    "dates": {
      "decision_date": "2020-05-15",
      "publication_date": "2020-06-01",
      "filing_date": "2019-03-10",
      "hearing_dates": ["2019-09-15", "2020-02-20"]
    },
    "procedural_posture": {
      "is_appeal": true,
      "is_revision": false,
      "is_first_instance": false,
      "prior_proceedings": ["P-123/2018"]
    }
  },
  "complex_facts": {
    "legal_issues": [
      "Tumačenje članka 110 Zakona o parničnom postupku",
      "Valjanost ugovora o najmu"
    ],
    "holding": "Žalba se odbija kao neosnovana. Potvrđuje se prvostupanjska presuda.",
    "key_findings": [
      "Sud je utvrdio da je ugovor o najmu valjan",
      "Tuženik nije ispunio ugovorne obveze"
    ],
    "legal_grounds": [
      "Članak 110 Zakona o parničnom postupku",
      "Članak 567 Zakona o obveznim odnosima"
    ],
    "arguments": {
      "plaintiff": [
        "Tuženik nije platio najamninu u propisanom roku",
        "Tužitelju pripada naknada štete"
      ],
      "defendant": [
        "Ugovor je ništetan zbog nedostatka forme",
        "Ne postoji obveza plaćanja"
      ]
    },
    "relief_sought": "Tužitelj traži raskid ugovora i naknadu štete u iznosu od 50.000,00 kn",
    "relief_granted": "Sud je usvojio tužbeni zahtjev u cijelosti",
    "standard_of_review": "Slobodna ocjena dokaza",
    "key_evidence": [
      "Ugovor o najmu od 15.03.2018",
      "Uplatnice za najamninu",
      "Svjedočenje Petra Perića"
    ],
    "dissent": false,
    "summary": "Vrhovni sud je potvrdio prvostupanjsku presudu kojom je usvojen zahtjev za raskid ugovora o najmu zbog neplaćanja najamnine. Sud je utvrdio valjanost ugovora i obveze plaćanja."
  },
  "extraction_method": "hybrid",
  "from_cache": false,
  "performance": {
    "total_time": 2.45
  }
}
```

---

#### 2. `decision.compare_facts`

Compare legal facts between two court decisions.

**Input:**
```json
{
  "decision_id_1": "01HF123ABC...",
  "decision_id_2": "01HF456DEF..."
}
```

**Parameters:**
- `decision_id_1` (required): First court decision document ID
- `decision_id_2` (required): Second court decision document ID (must be different)

**Output:**
```json
{
  "success": true,
  "decision1": {
    "id": "01HF123ABC...",
    "case_number": "Rev 123/2020"
  },
  "decision2": {
    "id": "01HF456DEF...",
    "case_number": "Rev 456/2021"
  },
  "basic_similarity": {
    "same_court": true,
    "same_jurisdiction": true,
    "same_decision_type": true,
    "same_procedural_posture": {
      "is_appeal": true,
      "is_revision": false,
      "is_first_instance": false
    },
    "shared_parties": {
      "plaintiffs": ["Marko Marić"],
      "defendants": []
    }
  },
  "complex_similarity": {
    "shared_legal_issues": [
      "Tumačenje članka 110 Zakona o parničnom postupku"
    ],
    "shared_legal_grounds": [
      "Članak 110 Zakona o parničnom postupku"
    ],
    "similar_relief_sought": true,
    "similar_holdings": false
  }
}
```

---

### PHP Service API

#### FactExtractionService

```php
use App\Services\FactExtractionService;

$factService = app(FactExtractionService::class);

// Extract facts from a decision
$facts = $factService->extractFacts($decisionId, [
    'use_llm' => true,      // Use LLM for complex extraction
    'use_cache' => true,    // Enable caching
    'cache_ttl' => 60,      // Cache for 60 minutes
]);

// Compare facts between two decisions
$comparison = $factService->compareDecisionFacts($decisionId1, $decisionId2);

// Batch extract facts from multiple decisions
$batch = $factService->batchExtractFacts([
    '01HF123ABC...',
    '01HF456DEF...',
    '01HF789GHI...',
], [
    'use_llm' => true,
    'use_cache' => true,
]);
```

#### DecisionSearchService Integration

```php
use App\Services\DecisionSearchService;

$searchService = app(DecisionSearchService::class);

// Extract facts
$facts = $searchService->extractFacts($decisionId);

// Compare facts
$comparison = $searchService->compareFacts($decisionId1, $decisionId2);

// Comprehensive analysis (citations + facts)
$analysis = $searchService->comprehensiveAnalysis($decisionId, [
    'use_llm' => true,
]);
```

---

## Extraction Methods

### Pattern-Based Extraction

Fast, reliable extraction using regex patterns for:

**Party Extraction:**
- Croatian patterns: `tužitelj`, `tuženik`, `predlagatelj`, `žalitelj`, `protivnik`
- Extracts names following party role indicators
- Deduplicates extracted parties

**Date Extraction:**
- Filing date: `podnesen(a) dana`, `podnesak od`, `tužba od`
- Hearing dates: `rasprava održana`, `ročište održano`, `saslušanje održano`
- Converts Croatian date format (DD.MM.YYYY) to ISO 8601 (YYYY-MM-DD)

**Procedural Posture:**
- Appeal detection: `žalba protiv`, `povodom žalbe`
- Revision detection: `revizija protiv`
- First instance: `prvom stupnju`, `prvostupanjska odluka`
- Prior proceedings: References to lower court decisions

### LLM-Based Extraction

Intelligent extraction using GPT-4o for complex legal analysis:

**Process:**
1. **Content Sampling**: Uses first 8000 characters for LLM processing
2. **Structured Prompt**: Provides clear JSON schema for extraction
3. **JSON Response**: Enforces JSON output format
4. **Validation**: Validates and parses JSON response
5. **Error Handling**: Graceful fallback if LLM extraction fails

**LLM Prompt Structure:**
```
Analyze this Croatian court decision and extract:
- Legal issues
- Holding/conclusion
- Key factual findings
- Legal grounds cited
- Arguments (plaintiff & defendant)
- Relief sought/granted
- Standard of review
- Key evidence
- Dissenting opinions
- Brief summary
```

**LLM Configuration:**
- Model: `gpt-4o` (latest, most capable)
- Temperature: `0.1` (low for consistency)
- Response format: `json_object` (enforced JSON)
- Language: Croatian (preserves original legal terminology)

---

## Performance & Caching

### Performance Characteristics

| Operation | Average Time | Notes |
|-----------|-------------|-------|
| Pattern-based extraction | 50-100ms | Fast, no external API |
| LLM-based extraction | 2-4 seconds | Depends on OpenAI API |
| Complete extraction (hybrid) | 2-5 seconds | Combined approach |
| Fact comparison | 4-8 seconds | Two extractions + comparison |
| Batch extraction (10 items) | 20-50 seconds | Parallel processing possible |

### Caching Strategy

**Default Cache Settings:**
- TTL: 60 minutes
- Cache key: `fact_extraction:{decision_id}:{method}`
- Separate caches for LLM and non-LLM extraction

**Cache Behavior:**
```php
// First call: Extracts and caches (2-5 seconds)
$facts = $factService->extractFacts($decisionId);

// Second call: Returns from cache (<10ms)
$facts = $factService->extractFacts($decisionId);

// Force fresh extraction
$facts = $factService->extractFacts($decisionId, ['use_cache' => false]);
```

**Cache Invalidation:**
- Manual: Clear cache when decision is updated
- Automatic: TTL expiration after 60 minutes
- Custom TTL: Configure via `cache_ttl` option

---

## Use Cases

### 1. Case Research

Extract facts to quickly understand a decision:

```php
$facts = $searchService->extractFacts($decisionId);

// Get quick summary
echo $facts['complex_facts']['summary'];

// Identify legal issues
foreach ($facts['complex_facts']['legal_issues'] as $issue) {
    echo "Issue: $issue\n";
}

// Understand the outcome
echo $facts['complex_facts']['holding'];
```

### 2. Precedent Analysis

Compare facts to find similar cases:

```php
// Get facts from target decision
$targetFacts = $searchService->extractFacts($targetDecisionId);

// Search for similar decisions
$similar = $searchService->findSimilar($targetDecisionId);

// Compare facts with each similar decision
foreach ($similar['results'] as $candidate) {
    $comparison = $searchService->compareFacts(
        $targetDecisionId,
        $candidate['decision']['id']
    );

    if ($comparison['complex_similarity']['similar_holdings']) {
        echo "Found decision with similar holding!\n";
    }
}
```

### 3. Legal Issue Clustering

Group decisions by legal issues:

```php
$decisions = [...]; // List of decision IDs
$batch = $factService->batchExtractFacts($decisions);

$issueGroups = [];
foreach ($batch['results'] as $decisionId => $facts) {
    foreach ($facts['complex_facts']['legal_issues'] as $issue) {
        $issueGroups[$issue][] = $decisionId;
    }
}

// Find most common legal issues
arsort($issueGroups);
```

### 4. Argument Mining

Extract arguments for legal strategy:

```php
$facts = $searchService->extractFacts($decisionId);

$plaintiffArgs = $facts['complex_facts']['arguments']['plaintiff'];
$defendantArgs = $facts['complex_facts']['arguments']['defendant'];

// Analyze successful arguments
if ($facts['complex_facts']['relief_granted']) {
    echo "Successful plaintiff arguments:\n";
    foreach ($plaintiffArgs as $arg) {
        echo "- $arg\n";
    }
}
```

### 5. Evidence Analysis

Track key evidence across decisions:

```php
$facts = $searchService->extractFacts($decisionId);

$evidence = $facts['complex_facts']['key_evidence'];

// Categorize evidence types
foreach ($evidence as $item) {
    if (stripos($item, 'ugovor') !== false) {
        $contracts[] = $item;
    } elseif (stripos($item, 'svjedočenje') !== false) {
        $testimony[] = $item;
    }
}
```

---

## Integration with Similarity Detection

Fact extraction enhances similarity detection by providing additional comparison dimensions:

### Enhanced Similarity Score

```php
// Find similar decisions
$similar = $searchService->findSimilar($decisionId);

// For each similar decision, compare facts
foreach ($similar['results'] as &$result) {
    $factComparison = $searchService->compareFacts(
        $decisionId,
        $result['decision']['id']
    );

    // Calculate fact-based similarity
    $factSimilarity = 0;
    if ($factComparison['basic_similarity']['same_court']) $factSimilarity += 0.1;
    if ($factComparison['basic_similarity']['same_decision_type']) $factSimilarity += 0.1;
    if (count($factComparison['complex_similarity']['shared_legal_issues']) > 0) $factSimilarity += 0.3;
    if (count($factComparison['complex_similarity']['shared_legal_grounds']) > 0) $factSimilarity += 0.3;
    if ($factComparison['complex_similarity']['similar_holdings']) $factSimilarity += 0.2;

    $result['fact_similarity'] = $factSimilarity;

    // Update composite score (40% existing + 60% fact-based)
    $result['enhanced_similarity'] =
        $result['composite_similarity'] * 0.4 +
        $factSimilarity * 0.6;
}

// Re-sort by enhanced similarity
usort($similar['results'], fn($a, $b) =>
    $b['enhanced_similarity'] <=> $a['enhanced_similarity']
);
```

---

## Error Handling

### Graceful Degradation

The system handles errors gracefully:

**LLM Extraction Failure:**
```php
{
  "basic_facts": { ... },  // Still available
  "complex_facts": {
    "error": "LLM extraction unavailable"
  },
  "extraction_method": "pattern-based"
}
```

**Decision Not Found:**
```php
{
  "success": false,
  "error": "Decision not found"
}
```

**Batch Processing:**
```php
{
  "success": false,  // false if any failed
  "total": 10,
  "extracted": 8,
  "failed": 2,
  "results": { ... },  // Successful extractions
  "errors": {          // Failed extractions
    "01HF123...": "Decision not found",
    "01HF456...": "LLM API error"
  }
}
```

### Logging

All errors are logged for debugging:

```php
Log::warning('LLM-based fact extraction failed', [
    'decision_id' => $decisionId,
    'error' => $e->getMessage(),
]);

Log::error('Batch fact extraction failed for decision', [
    'decision_id' => $decisionId,
    'error' => $e->getMessage(),
]);
```

---

## Security Considerations

### Data Privacy

- Extracted facts are cached (consider GDPR implications)
- Personal information in party names is extracted as-is
- No additional anonymization applied

### API Key Security

- OpenAI API key stored securely in `.env`
- Never exposed in logs or responses
- Rate limiting prevents API abuse

### Input Validation

- Decision IDs validated before processing
- Content length limited for LLM processing (8000 chars)
- JSON response validated before returning

---

## Best Practices

### 1. Use Caching

Enable caching for repeated queries:
```php
$facts = $factService->extractFacts($decisionId, [
    'use_cache' => true,
    'cache_ttl' => 120,  // 2 hours for frequently accessed decisions
]);
```

### 2. Batch Processing

Process multiple decisions efficiently:
```php
$batch = $factService->batchExtractFacts($decisionIds, [
    'use_llm' => true,
    'use_cache' => true,
]);
```

### 3. Conditional LLM Use

Disable LLM for quick basic facts:
```php
$basicFacts = $factService->extractFacts($decisionId, [
    'use_llm' => false,  // Only pattern-based extraction
]);
```

### 4. Error Handling

Always check success status:
```php
$facts = $factService->extractFacts($decisionId);

if (!$facts['success']) {
    Log::error('Fact extraction failed', [
        'decision_id' => $decisionId,
        'error' => $facts['error'],
    ]);
    return;
}

// Process facts...
```

### 5. Comprehensive Analysis

Use the comprehensive method for full insight:
```php
// Gets both citations and facts in one call
$analysis = $searchService->comprehensiveAnalysis($decisionId);

$citations = $analysis['citations'];
$facts = $analysis['facts'];
```

---

## Limitations

### Pattern-Based Extraction

- **Language Dependent**: Croatian patterns only
- **Format Sensitive**: Requires consistent document formatting
- **Limited Context**: Cannot understand semantic meaning

### LLM-Based Extraction

- **API Dependency**: Requires OpenAI API availability
- **Cost**: Each extraction costs ~$0.01-0.03 (GPT-4o pricing)
- **Latency**: 2-4 seconds per extraction
- **Content Limit**: First 8000 characters only
- **Language**: Best results with Croatian legal texts
- **Consistency**: May vary slightly between runs

### General Limitations

- **Accuracy**: Not 100% accurate, requires human review for critical decisions
- **Completeness**: May miss facts not explicitly stated
- **Updates**: Cached results don't reflect decision updates

---

## Future Enhancements

### Planned Features

1. **Multi-Language Support**: Extend to other languages
2. **Custom Extraction Templates**: User-defined fact schemas
3. **Fact Validation**: Cross-reference with database
4. **Timeline Generation**: Automatic timeline from extracted dates
5. **Party Resolution**: Link to party database
6. **Evidence Categorization**: Automatic evidence type classification
7. **Argument Graph**: Visualize argument structure
8. **Fact-Based Search**: Search decisions by specific facts

### Performance Improvements

1. **Streaming**: Stream LLM responses for faster TTFB
2. **Parallel Processing**: Parallelize batch extractions
3. **Smart Caching**: Predict which facts to pre-extract
4. **Incremental Extraction**: Update only changed facts

---

## Related Documentation

- [Citation Extraction and Similarity Detection](./CITATION_EXTRACTION_AND_SIMILARITY.md)
- [Citation and Similarity Flow Architecture](./CITATION_AND_SIMILARITY_FLOW.md)
- [Court Decision Refactoring](./COURT_DECISION_REFACTORING.md)
- [MCP Tools Documentation](./MCP_TOOLS.md)
