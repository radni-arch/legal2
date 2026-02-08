# Evidence Recontextualization Module

## Overview

The Evidence Recontextualization Module is a defensive legal tool that detects and counters prosecutorial selective presentation of evidence in Croatian criminal proceedings. When prosecutors present only incriminating excerpts while omitting exculpatory context, this module restores the full picture based on actual evidence.

**Version**: 1.0
**Sprint**: 3
**Status**: Production Ready
**Legal System**: Croatian (Republika Hrvatska)

---

## What is Recontextualization?

### Definition

**Recontextualization** is the process of restoring the FULL CONTEXT of evidence when prosecutors selectively present only portions that appear incriminating.

**Example**:
- **Prosecutor Shows**: "I'll get the stuff tonight"
- **Full Context**: Conversation about buying groceries for a friend
- **Recontextualization**: "The defendant was discussing a legitimate errand to pick up groceries, not an illegal activity. The full SMS conversation clearly shows 'stuff' refers to milk and bread."

### Why It Matters

Prosecutors sometimes present evidence in misleading ways by:
- Showing only one message from a conversation
- Highlighting specific timestamps while ignoring the timeline
- Quoting partial witness statements
- Displaying photos without context
- Presenting selective financial transactions

**This violates**:
- **ZKP Članak 9** - Prosecution must present ALL relevant evidence (incriminating AND exculpatory)
- **Ustav RH Članak 29** - Right to fair trial requires complete presentation

**Recontextualization restores fairness** by showing what the prosecutor left out.

---

## Ethical Framework

### ✅ Ethical Recontextualization (What We Do)

**Based on ACTUAL evidence:**
- Showing complete SMS conversation (full message thread)
- Revealing full timeline with proper timestamps
- Providing complete witness statements (full testimony)
- Showing full financial records (complete transaction history)
- Including metadata (GPS, timestamps, EXIF data)

**Examples**:
1. **SMS**: Showing all messages before and after the excerpt prosecutor selected
2. **Photos**: Including metadata showing photo taken 2 hours before crime
3. **Statements**: Providing full witness quote, not just incriminating portion
4. **Financial**: Showing legitimate transaction that explains suspicious withdrawal

### ❌ NOT Recontextualization (What We DON'T Do)

**The module will NEVER:**
- ❌ Fabricate messages or context that doesn't exist
- ❌ Create fake timestamps or metadata
- ❌ Alter witness statements
- ❌ Invent alternative timelines
- ❌ Manufacture evidence
- ❌ Distort clear, unambiguous facts

**This is not a tool for fabrication** - it's a tool for revealing truth.

### Legal and Ethical Boundaries

**Legitimate Use**:
```
Prosecutor: "Defendant withdrew 5,000 kuna on crime day"
Full Context: Bank records show withdrawal was loan repayment to brother (documented)
Recontextualization: ✅ ETHICAL - Based on actual bank records
```

**Illegitimate Use**:
```
Prosecutor: "Defendant withdrew 5,000 kuna on crime day"
Fabricated Context: "Defendant was withdrawing money for medical emergency"
NO EVIDENCE: ❌ UNETHICAL - Not based on actual evidence
```

---

## Selective Presentation Types Detected

### Type 1: Partial Messages (SMS/Email/Chat)

**Description**: Prosecutor shows only incriminating excerpt from longer conversation

**Examples**:

**Example 1: Drug Deal or Groceries?**
- **Prosecution Presents**: "I'll get the stuff tonight"
- **Full SMS Conversation**:
  ```
  [10:00] Friend: Can you pick up groceries after work?
  [10:05] Defendant: I'll get the stuff tonight
  [10:06] Friend: Thanks! We need milk, bread, and eggs
  [10:10] Defendant: Ok, see you at 7pm
  ```
- **Recontextualization**: "The full conversation reveals 'stuff' refers to groceries (milk, bread, eggs), not drugs or contraband. This is a routine errand for a friend."

**Example 2: Threat or Sports Banter?**
- **Prosecution Presents**: "I'm going to destroy you"
- **Full Context**:
  ```
  [WhatsApp group chat about soccer game]
  [14:20] Friend: Our team is going to win 5-0!
  [14:22] Defendant: I'm going to destroy you on the field 😂
  [14:25] Friend: Bring it on! See you at the match Saturday
  ```
- **Recontextualization**: "This was friendly sports banter in a group chat about an upcoming soccer game, not a threat to the alleged victim."

**Legal Basis**: ZKP Članak 9 (Objektivnost), ZKP Članak 331 (Slobodna ocjena dokaza)

---

### Type 2: Cherry-Picked Timestamps

**Description**: Prosecutor highlights specific times that seem incriminating while ignoring exculpatory timeline

**Examples**:

**Example 1: At Scene or Gone Before Crime?**
- **Prosecution Presents**: "Photo shows defendant at crime scene"
- **Full Timeline**:
  ```
  14:30 - Defendant photo at location (EXIF metadata)
  16:45 - Crime occurs at same location
  Timeline: Defendant left 2 hours 15 minutes BEFORE crime
  ```
- **Recontextualization**: "Photo metadata proves defendant was at the location at 14:30 but left well before the crime occurred at 16:45. This is exculpatory, not incriminating."

**Example 2: Call Records Show Alibi**
- **Prosecution Presents**: "Defendant's phone near crime scene"
- **Full Timeline**:
  ```
  20:00 - Phone tower ping near scene
  20:15 - Crime occurs
  20:18 - Defendant's phone shows video call with mother (30 min duration)
  Location: Defendant was 5km away during crime (GPS from call)
  ```
- **Recontextualization**: "Phone records show defendant was on video call with mother during the crime, GPS data from the call proves he was 5km away from scene."

**Legal Basis**: ZKP Članak 9 (Objektivnost), Ustav RH Članak 29 (Pravo na pravično suđenje)

---

### Type 3: Out-of-Context Media (Photos/Videos)

**Description**: Prosecutor presents photos or videos with misleading framing or without full context

**Examples**:

**Example 1: Suspicious Package or Pizza?**
- **Prosecution Presents**: "Defendant carrying suspicious package late at night"
- **Full Context**:
  ```
  Photo metadata: 22:30, October 15
  Full video: Shows pizza delivery logo on box
  Witness: Restaurant worker confirms defendant picked up family dinner order
  ```
- **Recontextualization**: "The 'suspicious package' was a pizza box from a local restaurant. Defendant was picking up family dinner, confirmed by restaurant receipt and witness."

**Example 2: Weapon or Tool?**
- **Prosecution Presents**: "Photo of defendant holding weapon-like object"
- **Full Video Context**:
  ```
  Object: Cordless drill for home repairs
  Setting: Hardware store parking lot, defendant loading purchases
  Receipt: Shows purchase of drill and home repair supplies
  ```
- **Recontextualization**: "Full video shows defendant purchased a cordless drill for home repairs at hardware store. Store receipt confirms legitimate purchase."

**Legal Basis**: ZKP Članak 331 (Slobodna ocjena dokaza - Court must evaluate full context)

---

### Type 4: Partial Witness Statements

**Description**: Prosecutor quotes only incriminating portion of witness testimony, omitting clarifying context

**Examples**:

**Example 1: Angry at Whom?**
- **Prosecution Quotes**: "Witness testified: 'He was very angry and aggressive'"
- **Full Witness Statement**:
  ```
  "He was very angry and aggressive at the referee during the soccer match.
   Everyone was yelling at the referee for a bad call. It was just sports
   passion, nothing violent. He never showed aggression toward anyone else.
   After the game, he was friendly and joking around with everyone."
  ```
- **Recontextualization**: "The full statement clarifies defendant was angry at a referee during a sports game (normal sports frustration), not at the alleged victim. The witness explicitly states defendant was friendly and showed no violence."

**Example 2: Argument or Discussion?**
- **Prosecution Quotes**: "They were arguing loudly"
- **Full Statement**:
  ```
  "They were arguing loudly about politics at the café, like everyone does.
   It was a friendly debate about the election. They were smiling and laughing.
   After 10 minutes, they ordered drinks together and continued chatting
   normally. There was no hostility at all."
  ```
- **Recontextualization**: "The complete statement reveals this was a friendly political debate at a café, not a hostile argument. The witness explicitly states they were smiling, laughing, and had drinks together afterwards."

**Legal Basis**: ZKP Članak 9 (Objektivnost - Prosecution must present full testimony)

---

### Type 5: Selective Financial Records

**Description**: Prosecutor shows suspicious transactions without legitimate explanations visible in full records

**Examples**:

**Example 1: Suspicious Withdrawal or Loan Repayment?**
- **Prosecution Presents**: "Defendant withdrew 5,000 kuna on day of alleged crime"
- **Full Bank Records**:
  ```
  Sept 1: Defendant received 5,000 kuna loan from brother (bank transfer with note: "Loan - repay by Oct 15")
  Oct 15: Defendant withdrew 5,000 kuna
  Oct 15: Cash deposit to brother's account (5,000 kuna with note: "Loan repayment")
  ```
- **Recontextualization**: "Full bank records show this was repayment of a documented loan from defendant's brother. The transaction is fully explained by prior loan receipt and subsequent repayment deposit."

**Example 2: Suspicious Deposits or Legitimate Income?**
- **Prosecution Presents**: "Large cash deposits in defendant's account"
- **Full Financial Records**:
  ```
  Defendant is freelance contractor (registered business)
  Deposits match invoices for completed work
  Tax declarations show all income properly reported
  Clients confirmed payments for services rendered
  ```
- **Recontextualization**: "The cash deposits are legitimate freelance contractor income, fully documented with invoices, tax declarations, and client confirmations. All income was properly reported to tax authorities."

**Legal Basis**: ZKP Članak 9 (Objektivnost), ZKP Članak 331 (Slobodna ocjena dokaza)

---

## How It Works

### System Components

```
Evidence Input
      ↓
┌─────────────────────────┐
│   ContextAnalyzer       │
│                         │
│ 1. Detect selective     │
│    presentation         │
│ 2. Identify omitted     │
│    context              │
│ 3. Find opportunities   │
└───────────┬─────────────┘
            ↓
┌─────────────────────────┐
│ RecontextualizationSvc  │
│                         │
│ 1. Generate defense     │
│    narrative            │
│ 2. Calculate credibility│
│ 3. Identify supporting  │
│    evidence             │
└───────────┬─────────────┘
            ↓
    Defense Strategy
```

### Step-by-Step Process

#### Step 1: Context Analysis (ContextAnalyzer)

**Inputs**:
- Prosecution's description of evidence
- Full available evidence content
- Metadata (timestamps, GPS, EXIF, etc.)
- Related documents and surrounding evidence

**Process**:
1. Compare prosecution presentation vs. full evidence
2. Identify type of selective presentation (5 types)
3. Calculate severity (0-100) of the omission
4. Document what was shown vs. what was omitted
5. Explain why the omission matters

**Output**:
```json
{
  "selective_presentation": {
    "detected": true,
    "type": "partial_message",
    "severity": 85,
    "what_prosecutor_showed": "I'll get the stuff tonight",
    "what_prosecutor_omitted": "Surrounding messages about grocery shopping",
    "why_omission_matters": "Full conversation shows 'stuff' = groceries, not drugs"
  }
}
```

---

#### Step 2: Defense Recontextualization (RecontextualizationService)

**Only proceeds if selective presentation detected.**

**Process**:
1. Generate 2-3 paragraph defense narrative showing full context
2. List key points from complete evidence
3. Provide alternative interpretation based on full facts
4. Identify supporting evidence (metadata, documents, witnesses)
5. Calculate credibility score (0-100)

**Credibility Scoring**:
```
Base Score: 50

+ 20 if prosecutor omitted significant context
+ 15 if supporting evidence exists for defense narrative
+ 15 if objective support (metadata, timestamps, documents)
+  5 bonus if high exculpatory value (total >= 150)
+  5 bonus if multiple types of supporting evidence (>= 3)

Maximum: 100
```

**Output**:
```json
{
  "defense_recontextualization": {
    "narrative": "While the prosecution presents only the isolated phrase...",
    "key_points": [
      "Full SMS conversation spans 4 messages over 10 minutes",
      "Context clearly shows discussion about grocery shopping",
      "Friend explicitly mentions 'milk and bread'"
    ],
    "alternative_interpretation": "The evidence shows legitimate errand, not criminal activity",
    "supporting_facts": [
      "Full SMS thread in evidence file",
      "Timestamps show casual conversation pace",
      "Friend's testimony confirms grocery request"
    ]
  },
  "credibility_score": 90,
  "credibility_level": "very_high",
  "recommended_use": "Strong defense argument - use prominently in trial"
}
```

---

## API Endpoint

### POST /api/evidence/recontextualize/{caseId}

**Purpose**: Recontextualize evidence to show full context

**Request**:
```json
{
  "evidence": {
    "id": "ev_sms1",
    "type": "communication",
    "description": "SMS message",
    "prosecution_description": "I'll get the stuff tonight",
    "full_content": "Full conversation:\n[10:00] Friend: Can you pick up groceries?\n[10:05] Defendant: I'll get the stuff tonight\n[10:06] Friend: Thanks, we need milk and bread"
  }
}
```

**Validation Rules**:
- `evidence` - required, object
- `evidence.id` - required, string
- `evidence.type` - required, string
- `evidence.description` - required, string
- `evidence.prosecution_description` - optional, string (how prosecution presents it)
- `evidence.full_content` - optional, string (complete evidence)
- `evidence.metadata` - optional, object (timestamps, GPS, EXIF, etc.)

**Response**:
```json
{
  "success": true,
  "data": {
    "evidence_id": "ev_sms1",
    "evidence_type": "communication",
    "context_analysis": {
      "selective_presentation": {
        "detected": true,
        "type": "partial_message",
        "severity": 85,
        "what_prosecutor_showed": "I'll get the stuff tonight",
        "what_prosecutor_omitted": "Messages about groceries (milk, bread)",
        "why_omission_matters": "Changes meaning from criminal to innocent",
        "legal_basis": "ZKP Članak 9 - Objektivnost"
      },
      "omitted_context": {
        "omissions_found": true,
        "omissions": [
          {
            "omitted_fact": "Friend requested grocery shopping",
            "where_in_full_evidence": "10:00 message: 'Can you pick up groceries?'",
            "how_it_changes_interpretation": "Shows legitimate errand, not crime",
            "exculpatory_value": 90
          }
        ]
      }
    },
    "recontextualization": {
      "recontextualization_needed": true,
      "defense_recontextualization": {
        "narrative": "While prosecution shows only 'I'll get the stuff tonight'...",
        "key_points": [...],
        "alternative_interpretation": "Legitimate grocery errand for friend"
      },
      "credibility_score": 90,
      "credibility_level": "very_high",
      "recommended_use": "Strong defense argument - use prominently in trial"
    }
  }
}
```

**Status Codes**:
- `200 OK` - Recontextualization completed
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

---

## Usage Examples

### Example 1: Detect Selective SMS Presentation

```php
use App\Modules\Evidence\EvidenceAnalysisModule;

$module = app(EvidenceAnalysisModule::class);

$evidence = [
    'id' => 'ev_sms1',
    'type' => 'communication',
    'description' => 'SMS conversation',
    'prosecution_description' => "I'll get the stuff tonight",
    'full_content' => <<<SMS
Full SMS conversation:
[10:00] Friend: Can you pick up groceries after work?
[10:05] Defendant: I'll get the stuff tonight
[10:06] Friend: Thanks! We need milk, bread, and eggs
[10:10] Defendant: Ok, see you at 7pm
SMS,
];

$result = $module->recontextualizeEvidence($caseId, $evidence);

// Check if selective presentation detected
if ($result['context_analysis']['selective_presentation']['detected']) {
    echo "SELECTIVE PRESENTATION DETECTED!\n";
    echo "Type: " . $result['context_analysis']['selective_presentation']['type'] . "\n";
    echo "Severity: " . $result['context_analysis']['selective_presentation']['severity'] . "/100\n";

    // Check recontextualization quality
    if ($result['recontextualization']['credibility_score'] >= 70) {
        echo "\nHIGH-CREDIBILITY DEFENSE NARRATIVE AVAILABLE\n";
        echo "Score: " . $result['recontextualization']['credibility_score'] . "/100\n";
        echo "Level: " . $result['recontextualization']['credibility_level'] . "\n";

        // Display defense narrative
        echo "\nDefense Narrative:\n";
        echo $result['recontextualization']['defense_recontextualization']['narrative'] . "\n";

        // Show key differences
        echo "\nKey Differences:\n";
        foreach ($result['recontextualization']['key_differences'] as $diff) {
            echo "Aspect: " . $diff['aspect'] . "\n";
            echo "Prosecution: " . $diff['prosecution'] . "\n";
            echo "Defense: " . $diff['defense'] . "\n";
            echo "Significance: " . $diff['significance'] . "\n\n";
        }
    }
}
```

---

### Example 2: Timeline Analysis with Metadata

```php
use App\Modules\Evidence\EvidenceAnalysisModule;

$module = app(EvidenceAnalysisModule::class);

$evidence = [
    'id' => 'ev_photo1',
    'type' => 'photo',
    'description' => 'Photo of defendant at location',
    'prosecution_description' => 'Defendant present at scene of crime',
    'full_content' => 'Photo shows defendant at location with visible landmarks',
    'metadata' => [
        'timestamp' => '2024-10-15T14:30:00Z', // EXIF data
        'location' => 'GPS: 45.8150° N, 15.9819° E', // Zagreb coordinates
        'camera' => 'iPhone 12',
        'original_filename' => 'IMG_2024_10_15_14_30.jpg',
    ],
    'additional_context' => [
        'crime_time' => '2024-10-15T16:45:00Z', // Crime occurred 2h 15min AFTER photo
        'witnesses' => 'Three witnesses confirm defendant left location at 14:45',
    ],
];

$result = $module->recontextualizeEvidence($caseId, $evidence);

// Check for cherry-picked timeline
if ($result['context_analysis']['selective_presentation']['type'] === 'cherry_picked_timeline') {
    echo "TIMELINE MANIPULATION DETECTED\n";

    // Show timeline reconstruction
    echo "\nTIMELINE:\n";
    echo "14:30 - Photo taken (EXIF metadata)\n";
    echo "14:45 - Defendant left (witness testimony)\n";
    echo "16:45 - Crime occurred (2h 15min later)\n";

    // Credibility check
    if ($result['recontextualization']['credibility_score'] >= 80) {
        echo "\nSTRONG ALIBI EVIDENCE\n";
        echo "Credibility: " . $result['recontextualization']['credibility_score'] . "/100\n";
        echo "Recommendation: " . $result['recontextualization']['recommended_use'] . "\n";
    }
}
```

---

### Example 3: Comprehensive Evidence Analysis

```php
use App\Modules\Evidence\EvidenceAnalysisModule;

$module = app(EvidenceAnalysisModule::class);

// Analyze all evidence in case (includes recontextualization automatically)
$analysisResult = $module->analyzeEvidence($caseId, $evidence, $options);

// Find evidence with recontextualization opportunities
foreach ($analysisResult['evidence_analysis'] as $item) {
    $recon = $item['recontextualization'] ?? [];

    if ($recon['recontextualization_needed'] ?? false) {
        echo "Evidence ID: " . $item['evidence_id'] . "\n";
        echo "Selective Presentation Type: " . $recon['selective_presentation_type'] . "\n";
        echo "Credibility Score: " . $recon['credibility_score'] . "/100\n";

        // High-value recontextualizations (score >= 80)
        if ($recon['credibility_score'] >= 80) {
            echo "⭐ HIGH-VALUE DEFENSE ARGUMENT\n";

            // Save to defense strategy document
            $defenseStrategy = [
                'evidence_id' => $item['evidence_id'],
                'narrative' => $recon['defense_recontextualization']['narrative'],
                'key_points' => $recon['defense_recontextualization']['key_points'],
                'credibility' => $recon['credibility_score'],
                'use_in' => [
                    'opening_statement' => true,
                    'cross_examination' => true,
                    'closing_argument' => true,
                ],
            ];

            // Export for trial preparation
            file_put_contents(
                "defense_strategy_{$item['evidence_id']}.json",
                json_encode($defenseStrategy, JSON_PRETTY_PRINT)
            );
        }

        echo "\n---\n\n";
    }
}
```

---

### Example 4: Integration with Misconduct Detection

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;
use App\Modules\Evidence\EvidenceAnalysisModule;

$misconductModule = app(ProsecutorialMisconductModule::class);
$evidenceModule = app(EvidenceAnalysisModule::class);

// Analyze misconduct
$misconduct = $misconductModule->analyzeMisconduct($caseId);

// Analyze evidence recontextualization
$evidence = [...]; // Evidence details
$recontextualization = $evidenceModule->recontextualizeEvidence($caseId, $evidence);

// Combined defense strategy
if ($misconduct['misconduct_detected'] &&
    $recontextualization['recontextualization']['recontextualization_needed']) {

    echo "POWERFUL COMBINED DEFENSE STRATEGY:\n\n";

    // Misconduct component
    echo "1. PROSECUTORIAL MISCONDUCT:\n";
    echo "   Violations: " . $misconduct['total_violations'] . "\n";
    echo "   Severity: " . $misconduct['severity_score'] . "/100 (" .
         $misconduct['severity_level'] . ")\n";

    foreach ($misconduct['instances'] as $violation) {
        echo "   - " . $violation['type'] . " (severity " . $violation['severity'] . ")\n";
    }

    // Recontextualization component
    echo "\n2. EVIDENCE RECONTEXTUALIZATION:\n";
    echo "   Credibility: " . $recontextualization['recontextualization']['credibility_score'] . "/100\n";
    echo "   Level: " . $recontextualization['recontextualization']['credibility_level'] . "\n";
    echo "   Selective Type: " . $recontextualization['context_analysis']['selective_presentation']['type'] . "\n";

    // Combined remedies
    echo "\n3. RECOMMENDED ACTIONS:\n";

    if ($misconduct['severity_score'] >= 80) {
        echo "   ✓ File dismissal motion (misconduct severity >= 80)\n";
        $dismissalMotion = $misconductModule->generateDismissalMotion($caseId);
    }

    if ($recontextualization['recontextualization']['credibility_score'] >= 70) {
        echo "   ✓ Use recontextualization in trial (credibility >= 70)\n";
        echo "   ✓ Include in opening statement\n";
        echo "   ✓ Use during cross-examination\n";
        echo "   ✓ Feature in closing argument\n";
    }

    // Generate combined report
    $report = [
        'case_id' => $caseId,
        'misconduct' => $misconduct,
        'recontextualization' => $recontextualization,
        'combined_strength' => 'very_high',
        'recommended_strategy' => 'Aggressive defense - seek dismissal or acquittal',
    ];

    file_put_contents("combined_defense_strategy_{$caseId}.json", json_encode($report, JSON_PRETTY_PRINT));
}
```

---

## Best Practices

### 1. Always Provide Full Content

```php
// Good: Complete evidence provided
$evidence = [
    'id' => 'ev1',
    'type' => 'communication',
    'prosecution_description' => 'Excerpt only',
    'full_content' => 'Complete message thread with all context',
    'metadata' => [...], // Include timestamps, sender info, etc.
];

// Bad: Insufficient context
$evidence = [
    'id' => 'ev1',
    'type' => 'communication',
    'description' => 'Some message', // Not enough for analysis
];
```

### 2. Include Metadata When Available

```php
$evidence = [
    'id' => 'ev_photo1',
    'type' => 'photo',
    'full_content' => 'Photo description',
    'metadata' => [
        'timestamp' => '2024-10-15T14:30:00Z', // EXIF data
        'location' => 'GPS coordinates',
        'camera_model' => 'iPhone 12',
        'original_filename' => 'IMG_2024.jpg',
        'file_size' => '2.5 MB',
    ],
    // Metadata strengthens recontextualization credibility
];
```

### 3. Use Credibility Scores to Prioritize

```php
$result = $module->recontextualizeEvidence($caseId, $evidence);
$score = $result['recontextualization']['credibility_score'] ?? 0;

if ($score >= 85) {
    // Very high credibility - use prominently
    echo "PRIMARY DEFENSE ARGUMENT\n";
    // Feature in opening, closing, cross-examination
} elseif ($score >= 70) {
    // High credibility - solid supporting argument
    echo "STRONG SUPPORTING ARGUMENT\n";
    // Use in defense strategy and closing
} elseif ($score >= 55) {
    // Moderate - use with other evidence
    echo "SUPPORTING ARGUMENT\n";
    // Combine with other evidence
} else {
    // Low credibility - use cautiously
    echo "WEAK ARGUMENT - Need more supporting evidence\n";
}
```

### 4. Document Everything

```php
// Save complete analysis for trial preparation
$result = $module->recontextualizeEvidence($caseId, $evidence);

// Create comprehensive documentation
$documentation = [
    'analysis_date' => now()->toDateTimeString(),
    'evidence_analyzed' => $evidence['id'],
    'selective_presentation' => $result['context_analysis']['selective_presentation'],
    'defense_narrative' => $result['recontextualization']['defense_recontextualization'],
    'credibility_assessment' => [
        'score' => $result['recontextualization']['credibility_score'],
        'level' => $result['recontextualization']['credibility_level'],
        'recommendation' => $result['recontextualization']['recommended_use'],
    ],
    'supporting_evidence' => $result['recontextualization']['supporting_evidence'],
    'legal_citations' => [
        'ZKP Članak 9 - Objektivnost',
        'ZKP Članak 331 - Slobodna ocjena dokaza',
        'Ustav RH Članak 29 - Pravo na pravično suđenje',
    ],
];

// Save for trial
file_put_contents("recontextualization_{$caseId}_{$evidence['id']}.json",
    json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
```

### 5. Verify AI Outputs

```php
$result = $module->recontextualizeEvidence($caseId, $evidence);

// Always verify AI-generated narrative against actual evidence
$narrative = $result['recontextualization']['defense_recontextualization']['narrative'] ?? '';
$fullContent = $evidence['full_content'] ?? '';

// Check that narrative facts are in full content
// Lawyer should ALWAYS review before using in court
if (!empty($narrative)) {
    echo "⚠️  LAWYER REVIEW REQUIRED\n";
    echo "Verify all facts in narrative match actual evidence\n";
    echo "Do not use without attorney approval\n";
}
```

---

## Testing

### Unit Tests

**File**: `tests/Feature/EvidenceModuleTest.php`

```bash
# Run recontextualization tests
php artisan test --filter=it_detects_selective_presentation_of_sms_messages
php artisan test --filter=it_identifies_omitted_timeline_context
php artisan test --filter=api_endpoint_recontextualizes_evidence
php artisan test --filter=does_not_recontextualize_when_no_selective_presentation
```

### Integration Tests

**File**: `tests/Feature/MisconductEvidenceIntegrationTest.php`

```bash
# Run integration tests
php artisan test --filter=hidden_evidence_detected_and_recontextualized
```

---

## Changelog

### Version 1.0 (Sprint 3) - October 2024

**Added**:
- Initial release of Evidence Recontextualization Module
- ContextAnalyzer service for selective presentation detection
- RecontextualizationService for defense narrative generation
- Detection of 5 types of selective presentation
- Credibility scoring (0-100) with objective algorithm
- API endpoint: POST /api/evidence/recontextualize/{caseId}
- Integration with EvidenceAnalysisModule
- Integration with ProsecutorialMisconductModule
- Comprehensive test suite

**Features**:
- AI-powered context analysis using GPT-4o-mini (temp 0.2)
- Defense narrative generation using GPT-4o (temp 0.4)
- Metadata support (timestamps, GPS, EXIF)
- Supporting evidence identification
- Croatian legal basis citations

**Ethical Safeguards**:
- No fabrication of context
- Evidence-based only
- Conservative AI temperatures
- Template fallbacks (no AI invention)
- Comprehensive logging

---

## Related Documentation

- [Prosecutorial Misconduct Module](./MISCONDUCT_MODULE.md) - Detecting prosecutorial violations
- [Sprint 3 Technical Documentation](./sprint-3-evidence-recontextualization.md) - Technical architecture
- [Integration Testing Guide](../tests/Feature/MisconductEvidenceIntegrationTest.php) - Testing both modules

---

**End of Evidence Recontextualization Documentation**
