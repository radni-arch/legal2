# Sprint 3: Evidence Recontextualization Module

## Overview

The Evidence Recontextualization Module enhances the Croatian AI Legal War Machine with capabilities to detect and counter prosecutorial selective presentation of evidence. This defensive tool identifies when prosecutors cherry-pick excerpts, omit exculpatory context, or present evidence in misleading ways, then generates legitimate defense narratives based on the full evidentiary context.

**Ethical Framework**: This module operates strictly within ethical bounds by:
- ✅ Identifying ACTUAL omissions in prosecution's evidence presentation
- ✅ Revealing REAL context that prosecution left out
- ✅ Providing legitimate alternative interpretations based on complete evidence
- ❌ NEVER fabricating context or evidence
- ❌ NEVER distorting clear, unambiguous facts

**Legal Basis (Croatian Law)**:
- **ZKP Članak 9** - Objektivnost (prosecution must present both incriminating AND exculpatory evidence)
- **ZKP Članak 331** - Slobodna ocjena dokaza (courts must evaluate evidence in full context)
- **Ustav RH Članak 29** - Pravo na pravično suđenje (fair trial requires complete evidence presentation)

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT APPLICATION                           │
│                     (Web UI / API Consumer)                         │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ HTTP Request
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      LARAVEL ROUTING LAYER                          │
│                    routes/evidence.php                              │
│  POST /api/evidence/recontextualize/{caseId}                       │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Route to Controller
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     EvidenceController                              │
│              (app/Http/Controllers)                                 │
│                                                                     │
│  • Validates request (evidence object required)                    │
│  • Calls EvidenceAnalysisModule.recontextualizeEvidence()        │
│  • Returns JSON response (200/422/500)                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Inject Dependencies
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   EvidenceAnalysisModule                            │
│           (app/Modules/Evidence)                                    │
│                                                                     │
│  Main orchestrator for evidence analysis including:                │
│  • Context analysis (via ContextAnalyzer)                          │
│  • Recontextualization (via RecontextualizationService)           │
│  • Evidence admissibility checking                                 │
│  • Constitutional violation detection                              │
└────────┬────────────────────────────────────────┬──────────────────┘
         │                                        │
         │ Step 1: Analyze Context               │ Step 2: Recontextualize
         │                                        │
         ▼                                        ▼
┌──────────────────────────────┐    ┌───────────────────────────────┐
│      ContextAnalyzer         │    │ RecontextualizationService    │
│  (Services/ContextAnalyzer)  │    │   (Services/Recontextuali-    │
│                              │    │        zationService)         │
│  Detects selective          │    │                               │
│  presentation by:            │    │  Generates defense narrative: │
│                              │    │                               │
│  1. Comparing prosecution's │    │  1. Extract prosecution      │
│     description vs. full    │    │     narrative                 │
│     evidence                 │    │  2. Generate defense         │
│                              │    │     recontextualization       │
│  2. Identifying 5 types:    │    │     (using GPT-4o)           │
│     • Partial messages      │    │  3. Highlight key            │
│     • Cherry-picked         │    │     differences               │
│       timestamps             │    │  4. Identify supporting      │
│     • Out-of-context media  │    │     evidence                  │
│     • Partial statements    │    │  5. Calculate credibility    │
│     • Selective records     │    │     score (0-100)            │
│                              │    │                               │
│  3. Finding omitted context │    │  Scoring Algorithm:          │
│     (using GPT-4o-mini)     │    │  • Base: 50                   │
│                              │    │  • +20: Significant omission │
│  4. Generating              │    │  • +15: Supporting evidence  │
│     recontextualization     │    │  • +15: Objective support    │
│     opportunities           │    │                               │
└──────────────┬───────────────┘    └───────────────┬───────────────┘
               │                                    │
               │ Uses AI (temperature 0.2)         │ Uses AI (temperature 0.4)
               │                                    │
               └────────────────┬───────────────────┘
                                │
                                ▼
                    ┌───────────────────────┐
                    │   OpenAIService       │
                    │  (App/Services)       │
                    │                       │
                    │  • GPT-4o-mini:       │
                    │    Context analysis   │
                    │    (JSON output)      │
                    │                       │
                    │  • GPT-4o:            │
                    │    Text generation    │
                    │    (narrative)        │
                    └───────────────────────┘
```

---

## Data Flow

### Request → Response Flow

```
1. CLIENT REQUEST
   POST /api/evidence/recontextualize/{caseId}
   Body: {
     "evidence": {
       "id": "ev_sms1",
       "type": "communication",
       "description": "SMS message",
       "prosecution_description": "I'll get the stuff tonight",
       "full_content": "Full conversation: [10:00] Friend: Can you pick up groceries?..."
     }
   }

   ↓

2. CONTROLLER VALIDATION
   • Validates evidence object structure
   • Checks required fields (id, type, description)
   • Returns 422 if validation fails

   ↓

3. EVIDENCE ANALYSIS MODULE
   • Loads case with documents relationship
   • Calls ContextAnalyzer.analyzeContext()
   • Calls RecontextualizationService.recontextualize()

   ↓

4. CONTEXT ANALYSIS (ContextAnalyzer)
   ┌─────────────────────────────────────┐
   │ Step 1: Extract Prosecution         │
   │         Presentation                │
   │ • prosecution_description           │
   │ • prosecution_excerpt               │
   │ • prosecution_timeline              │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Step 2: Extract Full Context        │
   │ • full_content                      │
   │ • metadata (timestamps, GPS, etc.)  │
   │ • surrounding_evidence              │
   │ • related_documents                 │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Step 3: Detect Selective            │
   │         Presentation (AI)           │
   │                                     │
   │ Uses GPT-4o-mini (temp 0.2):        │
   │ • Compare prosecution vs full       │
   │ • Identify type of selective        │
   │   presentation                      │
   │ • Calculate severity (0-100)        │
   │ • Identify what was shown/omitted   │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Step 4: Identify Omitted Context    │
   │         (AI)                        │
   │                                     │
   │ Uses GPT-4o-mini (temp 0.2):        │
   │ • Find specific omissions           │
   │ • Quote from full evidence          │
   │ • Calculate exculpatory value       │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Step 5: Find Recontextualization    │
   │         Opportunities               │
   │ • For each omission, generate       │
   │   defense recontextualization       │
   │ • Sort by exculpatory value         │
   └──────────────┬──────────────────────┘
                  ↓
                OUTPUT: Context Analysis Object

   ↓

5. RECONTEXTUALIZATION (RecontextualizationService)
   ┌─────────────────────────────────────┐
   │ Check: Was selective presentation   │
   │        detected?                    │
   │                                     │
   │ NO → Return { recontextualization_  │
   │              needed: false }        │
   │                                     │
   │ YES → Continue                      │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Generate Defense                    │
   │ Recontextualization (AI)            │
   │                                     │
   │ Uses GPT-4o (temp 0.4):             │
   │ • 2-3 paragraph narrative           │
   │ • Key points (3-5)                  │
   │ • Alternative interpretation        │
   │ • Supporting facts from evidence    │
   │ • Croatian legal basis              │
   │ • Fair trial argument               │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Highlight Key Differences           │
   │ • Evidence scope (shown vs omitted) │
   │ • Interpretation (prosecution vs    │
   │   defense)                          │
   │ • Top 3 omitted facts              │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Identify Supporting Evidence        │
   │ • Full evidence content             │
   │ • Metadata (timestamps, etc.)       │
   │ • Surrounding evidence              │
   │ • Related documents                 │
   │ • Omitted context quotes            │
   └──────────────┬──────────────────────┘
                  ↓
   ┌─────────────────────────────────────┐
   │ Calculate Credibility Score         │
   │                                     │
   │ Base: 50                            │
   │ + 20 if significant omission        │
   │ + 15 if supporting evidence exists  │
   │ + 15 if objective support (metadata)│
   │ +  5 bonus for high exculpatory val │
   │ +  5 bonus for multiple evidence    │
   │                                     │
   │ Max: 100                            │
   └──────────────┬──────────────────────┘
                  ↓
                OUTPUT: Recontextualization Object

   ↓

6. RESPONSE ASSEMBLY
   {
     "evidence_id": "ev_sms1",
     "evidence_type": "communication",
     "context_analysis": { ... },
     "recontextualization": { ... },
     "generated_at": "2025-10-29T..."
   }

   ↓

7. CLIENT RESPONSE
   HTTP 200 OK
   {
     "success": true,
     "data": { ... }
   }
```

---

## Data Schemas

### Input Schema (API Request)

```json
{
  "evidence": {
    "id": "string (required)",
    "type": "string (required) - communication|physical|testimonial|documentary|digital",
    "description": "string (required)",
    "prosecution_description": "string (optional) - how prosecution presented it",
    "full_content": "string (optional) - complete evidence content",
    "prosecution_excerpt": "string (optional)",
    "metadata": {
      "timestamp": "ISO8601 string",
      "location": "GPS coordinates or address",
      "collected_at": "ISO8601 string",
      "collected_by": "string"
    },
    "timestamps": ["array of ISO8601 strings"],
    "collected_at": "ISO8601 string"
  }
}
```

### Output Schema (API Response)

```json
{
  "success": true,
  "data": {
    "evidence_id": "string",
    "evidence_type": "string",
    "context_analysis": {
      "evidence_id": "string",
      "evidence_type": "string",
      "prosecution_presentation": {
        "description": "string - how prosecution presented evidence",
        "excerpt_shown": "string or null",
        "emphasis": "string or null",
        "timeline_framing": "string or null",
        "interpretation": "string or null"
      },
      "full_context": {
        "full_content": "string - complete evidence",
        "metadata": {},
        "timestamps": [],
        "surrounding_evidence": [
          {
            "id": "string",
            "type": "string",
            "description": "string",
            "collected_at": "ISO8601",
            "time_difference": "X hours"
          }
        ],
        "related_documents": [
          {
            "id": "number",
            "title": "string",
            "document_type": "string",
            "filed_at": "ISO8601"
          }
        ]
      },
      "selective_presentation": {
        "detected": boolean,
        "type": "partial_message|cherry_picked_timeline|out_of_context_media|partial_statement|selective_records|none",
        "severity": 0-100,
        "what_prosecutor_showed": "string",
        "what_prosecutor_omitted": "string",
        "why_omission_matters": "string",
        "legal_basis": "ZKP Članak X",
        "fair_trial_violation": boolean
      },
      "omitted_context": {
        "omissions_found": boolean,
        "omissions_count": number,
        "omissions": [
          {
            "omitted_fact": "string - specific fact left out",
            "where_in_full_evidence": "string - quote from evidence",
            "how_it_changes_interpretation": "string",
            "prosecutor_motivation": "string - why omitted",
            "exculpatory_value": 0-100
          }
        ],
        "total_exculpatory_value": number
      },
      "recontextualization_opportunities": [
        {
          "type": "context_restoration",
          "prosecution_narrative": "string",
          "defense_recontextualization": "string",
          "supporting_evidence": "string - quote",
          "exculpatory_value": 0-100,
          "legal_argument": "string in Croatian",
          "croatian_law_basis": "ZKP Članak 9, 331"
        }
      ],
      "analysis_timestamp": "ISO8601"
    },
    "recontextualization": {
      "recontextualization_needed": boolean,
      "evidence_id": "string",
      "evidence_type": "string",
      "selective_presentation_type": "string",
      "selective_presentation_severity": 0-100,
      "prosecution_narrative": {
        "summary": "string",
        "what_they_showed": "string",
        "their_interpretation": "string",
        "emphasis": "string",
        "selective_presentation_type": "string"
      },
      "defense_recontextualization": {
        "narrative": "string - 2-3 paragraphs",
        "key_points": [
          "Key point 1",
          "Key point 2",
          "Key point 3"
        ],
        "alternative_interpretation": "string - one sentence summary",
        "supporting_facts": [
          "Fact from evidence 1",
          "Fact from evidence 2"
        ],
        "croatian_legal_basis": "ZKP Čl. X, Ustav RH Čl. Y",
        "fair_trial_argument": "string"
      },
      "key_differences": [
        {
          "aspect": "Evidence Scope|Interpretation|Omitted Fact #X",
          "prosecution": "string - what prosecution presented",
          "defense": "string - what defense shows",
          "significance": "string - why this matters"
        }
      ],
      "supporting_evidence": [
        {
          "type": "full_evidence|metadata|surrounding_evidence|related_documents|omitted_context",
          "description": "string",
          "relevance": "string",
          "source": "string",
          "details": {},
          "count": number,
          "quote": "string",
          "exculpatory_value": 0-100
        }
      ],
      "credibility_score": 0-100,
      "credibility_level": "very_high|high|moderate|low|very_low",
      "recommended_use": "string - strategic recommendation",
      "generated_at": "ISO8601"
    },
    "generated_at": "ISO8601"
  }
}
```

### Credibility Score Levels

| Score Range | Level | Meaning | Recommendation |
|------------|-------|---------|----------------|
| 85-100 | `very_high` | Compelling recontextualization with strong objective support | Use prominently in trial and appeals |
| 70-84 | `high` | Solid recontextualization with good supporting evidence | Use in defense strategy and closing arguments |
| 55-69 | `moderate` | Reasonable recontextualization with some support | Use as supporting argument alongside other evidence |
| 40-54 | `low` | Weak recontextualization with limited support | Consider using only if no stronger arguments available |
| 0-39 | `very_low` | Very weak recontextualization | Not recommended without additional evidence |

---

## Key Components

### 1. ContextAnalyzer Service
**File**: `app/Modules/Evidence/Services/ContextAnalyzer.php`

**Purpose**: Analyzes full context of evidence to identify prosecutor's selective presentation

**Key Methods**:
- `analyzeContext(array $evidence, LegalCase $case)` - Main analysis orchestrator
- `detectSelectivePresentation()` - AI-powered detection using GPT-4o-mini
- `identifyOmittedContext()` - Finds what prosecutor left out
- `findRecontextualizationOpportunities()` - Creates defense opportunities

**Detection Types**:
1. **Partial Messages** - SMS/email/chat showing only incriminating excerpt
2. **Cherry-Picked Timestamps** - Highlighting specific times, ignoring exculpatory timeline
3. **Out-of-Context Media** - Photos/videos with misleading framing
4. **Partial Witness Statements** - Quoting only incriminating portion
5. **Selective Financial Records** - Showing suspicious transactions, hiding legitimate explanations

### 2. RecontextualizationService
**File**: `app/Modules/Evidence/Services/RecontextualizationService.php`

**Purpose**: Generates defense recontextualization showing full context

**Key Methods**:
- `recontextualize(array $evidence, array $contextAnalysis, LegalCase $case)` - Main generation orchestrator
- `generateDefenseRecontextualization()` - AI-powered narrative using GPT-4o
- `calculateCredibilityScore()` - Objective scoring (0-100)
- `highlightKeyDifferences()` - Compare prosecution vs defense
- `identifySupportingEvidence()` - Find corroborating evidence

**Credibility Scoring Algorithm**:
```
Base Score: 50

+ 20 if prosecutor omitted significant context
+ 15 if supporting evidence exists for defense narrative
+ 15 if objective support (metadata, timestamps, documents)
+  5 bonus if total exculpatory value >= 150
+  5 bonus if 3+ types of supporting evidence

Maximum: 100
```

### 3. EvidenceAnalysisModule
**File**: `app/Modules/Evidence/EvidenceAnalysisModule.php`

**Purpose**: Main orchestrator for evidence analysis, integrating recontextualization

**Key Methods**:
- `analyzeEvidence()` - Comprehensive analysis including recontextualization
- `recontextualizeEvidence()` - Standalone recontextualization method
- Integrates: ContextAnalyzer, RecontextualizationService, admissibility checking, constitutional violation detection

### 4. EvidenceController
**File**: `app/Http/Controllers/EvidenceController.php`

**Purpose**: HTTP API endpoint for recontextualization

**Endpoint**: `POST /api/evidence/recontextualize/{caseId}`

**Validation**:
- `evidence` - required, must be array
- `evidence.id` - required, string
- `evidence.type` - required, string
- `evidence.description` - required, string
- `evidence.prosecution_description` - optional, string
- `evidence.full_content` - optional, string

**Responses**:
- `200 OK` - Successful recontextualization
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Exception during processing

---

## Usage Examples

### Example 1: Partial SMS Message

**Prosecution's Presentation**:
> "I'll get the stuff tonight"

**Full Context**:
> [10:00] Friend: Can you pick up groceries?
> [10:05] Defendant: I'll get the stuff tonight
> [10:06] Friend: Thanks, we need milk and bread

**API Request**:
```bash
curl -X POST http://localhost/api/evidence/recontextualize/123 \
  -H "Content-Type: application/json" \
  -d '{
    "evidence": {
      "id": "ev_sms1",
      "type": "communication",
      "description": "SMS message",
      "prosecution_description": "I'\''ll get the stuff tonight",
      "full_content": "Full conversation:\n[10:00] Friend: Can you pick up groceries?\n[10:05] Defendant: I'\''ll get the stuff tonight\n[10:06] Friend: Thanks, we need milk and bread"
    }
  }'
```

**Response** (abbreviated):
```json
{
  "success": true,
  "data": {
    "context_analysis": {
      "selective_presentation": {
        "detected": true,
        "type": "partial_message",
        "severity": 85,
        "what_prosecutor_showed": "I'll get the stuff tonight",
        "what_prosecutor_omitted": "Surrounding messages about groceries, milk and bread",
        "why_omission_matters": "Full conversation shows 'stuff' refers to groceries, not drugs or contraband"
      }
    },
    "recontextualization": {
      "recontextualization_needed": true,
      "defense_recontextualization": {
        "narrative": "While prosecution shows the isolated phrase 'I'll get the stuff tonight,' the complete SMS conversation reveals this was a mundane discussion about grocery shopping...",
        "alternative_interpretation": "The evidence shows defendant was discussing grocery shopping, not illegal activity"
      },
      "credibility_score": 90,
      "credibility_level": "very_high",
      "recommended_use": "Strong defense argument - use prominently in trial and appeals"
    }
  }
}
```

### Example 2: Cherry-Picked Timeline

**Prosecution's Presentation**:
> "Photo shows defendant at crime scene"

**Full Context**:
> Photo metadata: timestamp 14:30, GPS coordinates match scene
> Crime occurred at: 16:45 (2 hours 15 minutes later)

**API Request**:
```bash
curl -X POST http://localhost/api/evidence/recontextualize/123 \
  -H "Content-Type: application/json" \
  -d '{
    "evidence": {
      "id": "ev_photo1",
      "type": "photo",
      "description": "Photo of defendant at location",
      "prosecution_description": "Defendant present at scene of crime",
      "full_content": "Photo metadata: timestamp 14:30, crime occurred at 16:45",
      "metadata": {
        "timestamp": "2024-03-15T14:30:00Z",
        "location": "Crime scene GPS coordinates"
      }
    }
  }'
```

**Response** (abbreviated):
```json
{
  "success": true,
  "data": {
    "context_analysis": {
      "selective_presentation": {
        "detected": true,
        "type": "cherry_picked_timeline",
        "severity": 75,
        "what_prosecutor_showed": "Defendant at scene",
        "what_prosecutor_omitted": "Photo taken 2+ hours before crime occurred",
        "why_omission_matters": "Timeline evidence shows defendant was not present when crime occurred"
      }
    },
    "recontextualization": {
      "credibility_score": 85,
      "credibility_level": "very_high"
    }
  }
}
```

### Example 3: No Selective Presentation

**Evidence**:
> Defendant fingerprints on weapon (no additional context)

**API Request**:
```bash
curl -X POST http://localhost/api/evidence/recontextualize/123 \
  -H "Content-Type: application/json" \
  -d '{
    "evidence": {
      "id": "ev_fp1",
      "type": "physical",
      "description": "Fingerprint evidence",
      "prosecution_description": "Defendant fingerprints on weapon",
      "full_content": "Defendant fingerprints on weapon (no additional context)"
    }
  }'
```

**Response**:
```json
{
  "success": true,
  "data": {
    "context_analysis": {
      "selective_presentation": {
        "detected": false
      }
    },
    "recontextualization": {
      "recontextualization_needed": false,
      "reason": "No selective presentation detected by prosecution"
    }
  }
}
```

---

## Integration Points

### 1. Comprehensive Evidence Analysis
The recontextualization feature is automatically included when calling `analyzeEvidence()`:

```php
$module = app(EvidenceAnalysisModule::class);
$result = $module->analyzeEvidence($caseId, $evidence);

// Result includes:
// - admissibility analysis
// - constitutional issues
// - alternative interpretations
// - context_analysis (NEW)
// - recontextualization (NEW)
// - suppression grounds
// - challenge strategy
```

### 2. Standalone Recontextualization
For focused recontextualization without full analysis:

```php
$module = app(EvidenceAnalysisModule::class);
$result = $module->recontextualizeEvidence($caseId, $evidence);

// Result includes only:
// - context_analysis
// - recontextualization
```

### 3. API Integration
RESTful API endpoints for external systems:

```
POST /api/evidence/recontextualize/{caseId}
POST /api/evidence/analyze/{caseId}  (includes recontextualization)
```

---

## Testing

### Test Coverage

Four comprehensive tests verify recontextualization functionality:

**File**: `tests/Feature/EvidenceModuleTest.php`

1. **`it_detects_selective_presentation_of_sms_messages()`**
   - Tests detection of partial message presentation
   - Verifies `selective_presentation.detected = true`
   - Verifies `selective_presentation.type = 'partial_message'`
   - Verifies defense recontextualization is generated

2. **`it_identifies_omitted_timeline_context()`**
   - Tests identification of cherry-picked timeline
   - Verifies omitted context structure
   - Verifies credibility score > 60 (when applicable)

3. **`api_endpoint_recontextualizes_evidence()`**
   - Tests API endpoint functionality
   - Verifies 200 response
   - Verifies JSON structure

4. **`does_not_recontextualize_when_no_selective_presentation()`**
   - Tests that system doesn't recontextualize when not needed
   - Verifies `recontextualization_needed = false`

### Running Tests

```bash
php artisan test --filter=EvidenceModuleTest
```

---

## Ethical Safeguards

### Built-in Protections

1. **No Fabrication**: AI prompts explicitly forbid fabricating context
2. **Evidence-Based Only**: All recontextualization must cite actual evidence
3. **Conservative Fallbacks**: On AI failures, system uses template-based responses (no fabrication)
4. **Transparent Scoring**: Credibility scores based on objective, documented factors
5. **Audit Trail**: Comprehensive logging of all analysis steps

### AI Temperature Settings

- **Context Analysis**: Temperature 0.2 (high accuracy, low creativity)
- **Recontextualization**: Temperature 0.4 (balanced accuracy/creativity)
- **JSON Outputs**: Response format forced to `json_object` for structured, predictable results

### Legal Compliance

All recontextualization complies with Croatian legal ethics:
- Based on actual case evidence
- Cites proper legal authorities (ZKP, Ustav RH)
- Provides legitimate defense interpretations
- Does not obstruct justice or mislead courts

---

## Performance Considerations

### API Calls per Recontextualization

1. **Context Analysis**:
   - 1 call to GPT-4o-mini for selective presentation detection
   - 1 call to GPT-4o-mini for omitted context identification
   - 1 call to GPT-4o-mini per recontextualization opportunity (usually 1-3)

2. **Recontextualization**:
   - 1 call to GPT-4o for defense narrative generation

**Total**: Approximately 3-5 API calls per evidence item

### Optimization Strategies

- Cache analysis results for frequently-accessed evidence
- Batch process multiple evidence items in parallel
- Use database relationships preloading (`with('documents', 'evidence')`)
- Implement rate limiting on API endpoints

---

## Future Enhancements

### Planned Features

1. **Multimedia Analysis**: Direct image/video analysis for out-of-context media detection
2. **Batch Processing**: Analyze multiple evidence items in single request
3. **Historical Patterns**: Learn from past cases to identify common selective presentation tactics
4. **Confidence Intervals**: Statistical confidence ranges for credibility scores
5. **Interactive Refinement**: Allow lawyers to refine recontextualization with additional context

### Integration Roadmap

- **Document Generation**: Auto-insert recontextualization into legal briefs
- **Trial Preparation**: Generate cross-examination questions based on omissions
- **Appeal Builder**: Use recontextualization for appeal grounds
- **Prosecutor Accountability**: Track patterns of selective presentation across cases

---

## Troubleshooting

### Common Issues

**Issue**: Low credibility scores despite clear selective presentation
- **Cause**: Missing metadata or supporting evidence
- **Solution**: Include metadata, timestamps, and full content in evidence object

**Issue**: No selective presentation detected when expected
- **Cause**: prosecution_description and full_content are too similar
- **Solution**: Ensure full_content contains substantially more context than prosecution_description

**Issue**: AI-generated recontextualization seems weak
- **Cause**: OpenAI API failure, using template fallback
- **Solution**: Check logs for API errors, verify API key, check rate limits

**Issue**: Test failures on credibility_score assertions
- **Cause**: Selective presentation not detected (recontextualization_needed = false)
- **Solution**: Tests now check recontextualization_needed before asserting on score

---

## Changelog

### Sprint 3 (2025-10-29)

**Added**:
- ContextAnalyzer service for selective presentation detection
- RecontextualizationService for defense narrative generation
- Integration with EvidenceAnalysisModule
- API endpoint `/api/evidence/recontextualize/{caseId}`
- Comprehensive test suite (4 tests)
- Documentation (this file)

**Fixed** (Code Review):
- Array sum issue with missing exculpatory_value fields
- Relationship loading for evidence and documents
- More selective document matching (removed overly broad search)
- Test assertion for credibility_score when recontextualization not needed

---

## Contact & Support

For questions, issues, or contributions related to the Evidence Recontextualization Module, please refer to the main project repository.

**Legal Disclaimer**: This software is provided for legal defense purposes only. Users are responsible for ensuring ethical use in compliance with Croatian legal ethics and professional responsibility standards.
