# Prosecutorial Misconduct Module

## Overview

The Prosecutorial Misconduct Module is a comprehensive system for detecting, analyzing, and documenting prosecutorial misconduct in Croatian criminal proceedings. Built specifically for the Croatian legal system, it identifies violations of the Zakon o kaznenom postupku (ZKP), Ustav Republike Hrvatske (Croatian Constitution), and prosecutorial ethics standards.

**Version**: 1.0
**Sprint**: 2
**Status**: Production Ready
**Legal System**: Croatian (Republika Hrvatska)

---

## Ethical Use Statement

### ✅ Legitimate Uses

This module is designed for **defensive legal purposes only**:

- **Detecting actual misconduct** based on evidence and legal standards
- **Protecting defendant rights** guaranteed by Croatian Constitution
- **Ensuring fair trials** under ZKP Article 29 (right to fair trial)
- **Holding prosecutors accountable** for violations of ZKP Article 9 (objectivity principle)
- **Generating legal motions** based on documented violations
- **Filing ethical complaints** for systematic violations

### ❌ Prohibited Uses

This module must **NOT** be used for:

- ❌ Fabricating false accusations against prosecutors
- ❌ Creating false evidence of misconduct
- ❌ Harassing prosecutors without legitimate basis
- ❌ Obstructing justice or interfering with prosecutions
- ❌ Filing frivolous complaints
- ❌ Political attacks on prosecutors

### Ethical Framework

The module operates under strict ethical constraints:

1. **Evidence-Based Detection**: All misconduct findings must be based on actual evidence
2. **Conservative Analysis**: System errs on side of caution to avoid false accusations
3. **Legal Standards**: All violations identified against established Croatian legal precedent
4. **Transparency**: All findings documented with specific legal citations
5. **Proportionality**: Remedies proportional to severity of violations

---

## Features

### 1. Misconduct Detection (6 Types)

The module detects six major categories of prosecutorial misconduct:

#### Type 1: Fabricated Probable Cause
**Description**: Prosecutor invents or fabricates justification for search, arrest, or prosecution
**Examples**:
- Fake "confidential informant" to justify search warrant
- Manufactured "anonymous tip" for arrest
- False affidavit statements

**Legal Basis**: ZKP Članak 9 (Objektivnost), Ustav RH Članak 32 (Zaštita osobne slobode)
**Typical Severity**: 85-95
**Primary Remedy**: Evidence suppression, dismissal of charges

#### Type 2: Hidden Evidence (Brady Violations)
**Description**: Prosecutor withholds exculpatory evidence from defense
**Examples**:
- Hiding witness statements favorable to defendant
- Withholding forensic evidence showing innocence
- Not disclosing alibi witnesses

**Legal Basis**: ZKP Članak 292 (Otkrivanje dokaza), Ustav RH Članak 29 (Pravo na pravično suđenje)
**Typical Severity**: 90-100
**Primary Remedy**: Dismissal, new trial, sanctions

#### Type 3: Backdated Documents
**Description**: Prosecutor creates or alters documents with false dates
**Examples**:
- Search warrant dated before actual approval
- Evidence report backdated to appear timely
- Witness statement altered with false timestamp

**Legal Basis**: ZKP Članak 9 (Objektivnost), KZ Članak 305 (Krivotvorenje dokumenta)
**Typical Severity**: 85-95
**Primary Remedy**: Evidence exclusion, dismissal, criminal charges

#### Type 4: Rights Violations
**Description**: Prosecutor violates defendant's constitutional rights
**Examples**:
- Interrogation without attorney present (after request)
- Denial of access to counsel
- Coerced statements

**Legal Basis**: Ustav RH Članak 29 (Pravo na obranu), ZKP Članak 177 (Pravo na branitelja)
**Typical Severity**: 70-90
**Primary Remedy**: Evidence suppression, retrial

#### Type 5: Prosecutor Threats/Lying
**Description**: Prosecutor makes threats or lies to defendant, witnesses, or court
**Examples**:
- Threatening defendant with harsher charges unless guilty plea
- Lying to court about evidence
- Intimidating defense witnesses

**Legal Basis**: Zakon o državnom odvjetništvu Članak 6, Kodeks profesionalne etike
**Typical Severity**: 80-95
**Primary Remedy**: Dismissal, complaint to State Attorney, judicial council complaint

#### Type 6: Misdemeanor Pretexting
**Description**: Prosecutor files minor charges as pretext to investigate serious crimes
**Examples**:
- Filing traffic violation to conduct drug search
- Using administrative violation to get arrest warrant
- Misdemeanor charge to circumvent search warrant requirements

**Legal Basis**: ZKP Članak 1 (Svrha zakona), Ustav RH Članak 32 (Zaštita osobne slobode)
**Typical Severity**: 60-80
**Primary Remedy**: Evidence suppression, charge dismissal

---

## Misconduct Types Summary Table

| Type | Legal Basis | Typical Severity | Primary Remedy |
|------|-------------|------------------|----------------|
| Fabricated Probable Cause | ZKP Čl. 9, Ustav RH Čl. 32 | 85-95 | Evidence suppression, dismissal |
| Hidden Evidence (Brady) | ZKP Čl. 292, Ustav RH Čl. 29 | 90-100 | Dismissal, retrial, sanctions |
| Backdated Documents | ZKP Čl. 9, KZ Čl. 305 | 85-95 | Evidence exclusion, criminal charges |
| Rights Violations | Ustav RH Čl. 29, ZKP Čl. 177 | 70-90 | Evidence suppression, retrial |
| Prosecutor Threats/Lying | Zakon o DO Čl. 6, Kodeks etike | 80-95 | Dismissal, ethics complaint |
| Misdemeanor Pretexting | ZKP Čl. 1, Ustav RH Čl. 32 | 60-80 | Evidence suppression, dismissal |

---

## API Endpoints

### 1. Analyze Misconduct

**Endpoint**: `POST /api/misconduct/analyze/{caseId}`

**Purpose**: Comprehensive analysis of prosecutorial misconduct in a case

**Request**:
```json
{
  "options": {
    "include_patterns": true,
    "generate_recommendations": true
  }
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "case_id": "123",
    "misconduct_detected": true,
    "total_violations": 3,
    "severity_score": 92,
    "severity_level": "critical",
    "instances": [
      {
        "type": "hidden_evidence",
        "severity": 95,
        "description": "Prosecutor withheld 3 exculpatory witness statements",
        "evidence": "Discovery response dated Oct 1 disclosed only 2 of 5 statements",
        "legal_basis": "ZKP Članak 292 - Otkrivanje dokaza",
        "impact": "Material exculpatory evidence withheld from defense"
      }
    ],
    "patterns": {
      "brady_pattern": {
        "total_brady_violations": 2,
        "is_systemic": true
      }
    },
    "recommended_actions": [
      {
        "action": "file_dismissal_motion",
        "priority": "urgent",
        "description": "File motion to dismiss based on egregious prosecutorial misconduct"
      }
    ],
    "dismissal_grounds": [
      {
        "violation_type": "hidden_evidence",
        "legal_basis": "ZKP Članak 175, 177 - Obustava postupka",
        "strength": 95
      }
    ]
  }
}
```

**Status Codes**:
- `200 OK` - Analysis completed successfully
- `404 Not Found` - Case ID not found
- `500 Internal Server Error` - Server error

---

### 2. Generate Dismissal Motion

**Endpoint**: `POST /api/misconduct/dismissal-motion/{caseId}`

**Purpose**: Generate formal motion to dismiss charges based on prosecutorial misconduct

**Request**: No body required

**Response**:
```json
{
  "success": true,
  "data": {
    "motion_type": "Prijedlog za obustavu kaznenog postupka",
    "case_id": "123",
    "motion_text": "PRIJEDLOG ZA OBUSTAVU KAZNENOG POSTUPKA\n\nPoštovani sude,\n\n...",
    "legal_basis": [
      "ZKP Članak 175 - Obustava postupka",
      "ZKP Članak 177 - Obustava zbog povrede procesnih odredaba",
      "Ustav RH Članak 29 - Pravo na pravično suđenje"
    ],
    "grounds": [
      {
        "ground": "Prosecutor withheld exculpatory evidence",
        "legal_citation": "ZKP Članak 292",
        "severity": 95
      }
    ],
    "filing_instructions": {
      "court": "Općinski sud u Zagrebu",
      "deadline": "8 days from discovery of violation",
      "required_attachments": [
        "Evidence of withheld statements",
        "Discovery correspondence"
      ]
    }
  }
}
```

---

### 3. Generate Complaint

**Endpoint**: `POST /api/misconduct/complaint/{caseId}`

**Purpose**: Generate formal complaint to State Attorney or Judicial Council

**Request**:
```json
{
  "complaint_type": "state_attorney"
}
```

**Complaint Types**:
- `state_attorney` - Complaint to State Attorney (Državno odvjetništvo)
- `judicial_council` - Complaint to Judicial Council (Državno sudbeno vijeće)

**Response**:
```json
{
  "success": true,
  "data": {
    "complaint_type": "state_attorney",
    "complaint_text": "PRIJAVA PROTIV ZAMJENIKA DRŽAVNOG ODVJETNIKA\n\n...",
    "recipient": "Državno odvjetništvo Republike Hrvatske",
    "recipient_address": "Gajeva 30a, 10000 Zagreb",
    "violations": [
      {
        "type": "hidden_evidence",
        "description": "Systematic withholding of exculpatory evidence",
        "legal_basis": "ZKP Članak 292"
      }
    ],
    "severity": "critical",
    "recommended_sanctions": [
      "Disciplinary proceedings",
      "Case reassignment",
      "Ethics review"
    ]
  }
}
```

---

### 4. Build Appeal

**Endpoint**: `POST /api/misconduct/appeal/{caseId}`

**Purpose**: Build appeal (žalba) citing prosecutorial misconduct

**Request**:
```json
{
  "appeal_type": "zalba",
  "conviction_date": "2024-10-15",
  "sentence": "6 months imprisonment"
}
```

**Appeal Types**:
- `zalba` - Standard appeal (žalba)
- `izvanredna_zalba` - Extraordinary appeal
- `ponavljanje` - Retrial motion

**Response**:
```json
{
  "success": true,
  "data": {
    "appeal_type": "žalba",
    "appeal_text": "ŽALBA PROTIV PRESUDE\n\n...",
    "grounds": [
      {
        "ground": "Prosecutorial misconduct - hidden evidence",
        "legal_basis": "ZKP Članak 382 - Razlozi za žalbu",
        "description": "Prosecutor withheld exculpatory evidence violating ZKP Članak 292"
      }
    ],
    "deadline": "15 days from receipt of judgment",
    "filing_court": "Županijski sud u Zagrebu"
  }
}
```

---

## Croatian Legal Framework

### Primary Legal Sources

#### 1. Zakon o kaznenom postupku (ZKP) - Criminal Procedure Act

**Article 9 - Objektivnost (Objectivity)**
> "Državni odvjetnik dužan je voditi računa ne samo o okolnostima koje terete okrivljenika, već i o onima koje ga oslobađaju ili olakšavaju njegovu odgovornost."

*"The State Attorney is obliged to take into account not only circumstances that incriminate the accused, but also those that exonerate or mitigate their responsibility."*

**Violations**: Hiding exculpatory evidence, fabricating probable cause, selective presentation

---

**Article 175 - Obustava postupka (Termination of Proceedings)**
> "Sud će rješenjem obustaviti postupak ako su ispunjeni uvjeti za osudu okrivljenika, ali postoje razlozi koji to sprječavaju."

*Grounds for dismissal include prosecutorial misconduct that violates fair trial rights.*

---

**Article 177 - Obustava zbog povrede procesnih odredaba**
> "Sud će obustaviti postupak ako su povrijeđene procesne odredbe koje onemogućavaju donošenje pravilne odluke."

*Court shall terminate proceedings if procedural violations prevent a correct decision.*

**Application**: Severe prosecutorial misconduct (severity >= 85) warrants dismissal

---

**Article 292 - Otkrivanje dokaza (Disclosure of Evidence)**
> "Državni odvjetnik dužan je prije glavne rasprave obavijestiti stranku o dokazima koje namjerava izvesti."

*State Attorney must disclose evidence to parties before trial.*

**Violations**: Hidden evidence, Brady violations

---

#### 2. Ustav Republike Hrvatske (Croatian Constitution)

**Article 29 - Pravo na pravično suđenje (Right to Fair Trial)**
> "Svatko ima pravo na jednaku zaštitu svojih prava i sloboda u potpuno neovisnom i nepristranom sudu."

*Everyone has the right to equal protection of their rights and freedoms in a fully independent and impartial court.*

**Application**: Prosecutorial misconduct violates fundamental fair trial rights

---

**Article 32 - Zaštita osobne slobode (Protection of Personal Liberty)**
> "Nitko ne može biti lišen slobode osim na način i iz razloga koji su određeni zakonom."

*No one may be deprived of liberty except in manner and for reasons determined by law.*

**Violations**: Fabricated probable cause, illegal searches/arrests

---

#### 3. Zakon o Državnom odvjetništvu (State Attorney Act)

**Article 6 - Načela (Principles)**
State Attorneys must act:
- In accordance with law and Constitution
- Objectively and impartially
- Professionally and conscientiously
- Respecting human dignity and rights

**Violations**: Threats, lying, unethical conduct

---

**Article 14 - Stegovni postupak (Disciplinary Proceedings)**
State Attorneys may face disciplinary action for:
- Violation of official duties
- Unethical conduct
- Actions damaging reputation of State Attorney's Office

**Application**: Basis for complaints to State Attorney

---

#### 4. Kodeks profesionalne etike državnih odvjetnika

**Professional Ethics Code for State Attorneys**

Key ethical obligations:
- **Objectivity** - Present all evidence, incriminating and exculpatory
- **Honesty** - Never deceive court, defendant, or defense counsel
- **Fairness** - Ensure fair trial for defendant
- **Integrity** - Maintain highest ethical standards

**Violations**: All forms of misconduct

---

## Components

### 1. ProsecutorialMisconductModule
**Location**: `app/Modules/Misconduct/ProsecutorialMisconductModule.php`

**Main orchestrator for misconduct detection and analysis.**

**Key Methods**:

```php
public function analyzeMisconduct(string $caseId, array $options = []): array
```
Comprehensive misconduct analysis. Returns all violations, patterns, severity score, and recommended actions.

```php
public function generateDismissalMotion(string $caseId): array
```
Generates formal motion to dismiss charges based on detected misconduct.

```php
public function generateComplaint(string $caseId, string $complaintType): array
```
Generates complaint to State Attorney or Judicial Council.

```php
public function buildAppeal(string $caseId, string $appealType): array
```
Builds appeal citing prosecutorial misconduct as grounds.

---

### 2. MisconductDetector
**Location**: `app/Modules/Misconduct/Services/MisconductDetector.php`

**Detects specific instances of prosecutorial misconduct.**

**Detection Methods**:
- `detectFabricatedProbableCause()` - Identifies fabricated justifications
- `detectHiddenEvidence()` - Finds Brady violations
- `detectBackdatedDocuments()` - Identifies document backdating
- `detectRightsViolations()` - Finds constitutional violations
- `detectProsecutorThreats()` - Identifies threats/lies
- `detectMisdemeanorPretexting()` - Finds pretext charges

**Technology**: Uses OpenAI GPT-4o-mini for pattern detection with temperature 0.2 for accuracy.

---

### 3. MisconductPatternAnalyzer
**Location**: `app/Modules/Misconduct/Services/MisconductPatternAnalyzer.php`

**Analyzes patterns across multiple violations.**

**Pattern Types**:
- **Brady Pattern** - Systematic evidence hiding
- **Rights Violations Pattern** - Repeated constitutional violations
- **Timing Suspicious Pattern** - Backdating patterns
- **Threat Pattern** - Systematic intimidation

**Purpose**: Identifies systemic misconduct requiring enhanced remedies (complaints, ethics reviews).

---

### 4. DismissalMotionGenerator
**Location**: `app/Modules/Misconduct/Services/DismissalMotionGenerator.php`

**Generates formal dismissal motions in Croatian.**

**Features**:
- Croatian legal language
- Proper legal citations (ZKP, Ustav RH)
- Specific violation documentation
- Filing instructions
- Required attachments list

**Technology**: Uses GPT-4o for high-quality Croatian legal text generation.

---

### 5. ComplaintGenerator
**Location**: `app/Modules/Misconduct/Services/ComplaintGenerator.php`

**Generates formal complaints to authorities.**

**Complaint Types**:
- **State Attorney** - Internal disciplinary complaint
- **Judicial Council** - External ethics complaint

**Features**:
- Proper recipient addressing
- Violation documentation
- Legal basis citations
- Recommended sanctions

---

### 6. AppealBuilder
**Location**: `app/Modules/Misconduct/Services/AppealBuilder.php`

**Builds appeals citing prosecutorial misconduct.**

**Appeal Types**:
- **Žalba** (Standard appeal)
- **Izvanredna žalba** (Extraordinary appeal)
- **Ponavljanje postupka** (Retrial motion)

**Features**:
- Misconduct as appeal grounds
- Legal citations (ZKP Članak 382)
- Filing deadlines
- Appellate court identification

---

## Usage Examples

### Example 1: Detect Hidden Evidence

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;

$module = app(ProsecutorialMisconductModule::class);

// Analyze case for misconduct
$result = $module->analyzeMisconduct($caseId);

// Check severity
if ($result['severity_score'] >= 85) {
    echo "SEVERE MISCONDUCT DETECTED\n";
    echo "Severity Level: " . $result['severity_level'] . "\n";
    echo "Total Violations: " . $result['total_violations'] . "\n";

    // Generate dismissal motion
    $dismissalMotion = $module->generateDismissalMotion($caseId);

    // Save motion to file
    file_put_contents(
        "dismissal_motion_{$caseId}.txt",
        $dismissalMotion['motion_text']
    );

    echo "Dismissal motion generated: dismissal_motion_{$caseId}.txt\n";
}

// Check for patterns
if (isset($result['patterns']['brady_pattern'])) {
    $brady = $result['patterns']['brady_pattern'];
    if ($brady['is_systemic'] ?? false) {
        echo "SYSTEMIC BRADY VIOLATIONS DETECTED\n";

        // Generate complaint to State Attorney
        $complaint = $module->generateComplaint($caseId, 'state_attorney');
        echo "Complaint generated - file with: " . $complaint['recipient'] . "\n";
    }
}

// Display all violations
foreach ($result['instances'] as $violation) {
    echo "\nViolation Type: " . $violation['type'] . "\n";
    echo "Severity: " . $violation['severity'] . "/100\n";
    echo "Description: " . $violation['description'] . "\n";
    echo "Legal Basis: " . $violation['legal_basis'] . "\n";
}
```

---

### Example 2: Generate Dismissal Motion

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;

$module = app(ProsecutorialMisconductModule::class);
$caseId = '123';

// Generate dismissal motion
$motion = $module->generateDismissalMotion($caseId);

echo "Motion Type: " . $motion['motion_type'] . "\n";
echo "\nMotion Text:\n";
echo $motion['motion_text'] . "\n";

echo "\nLegal Basis:\n";
foreach ($motion['legal_basis'] as $citation) {
    echo "- " . $citation . "\n";
}

echo "\nFiling Instructions:\n";
echo "Court: " . $motion['filing_instructions']['court'] . "\n";
echo "Deadline: " . $motion['filing_instructions']['deadline'] . "\n";

echo "\nRequired Attachments:\n";
foreach ($motion['filing_instructions']['required_attachments'] as $attachment) {
    echo "- " . $attachment . "\n";
}
```

---

### Example 3: Pattern Detection and Complaint

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;

$module = app(ProsecutorialMisconductModule::class);
$caseId = '123';

// Analyze for misconduct
$analysis = $module->analyzeMisconduct($caseId);

// Check for patterns
$hasPattern = false;
foreach ($analysis['patterns'] as $patternName => $pattern) {
    if (isset($pattern['is_systemic']) && $pattern['is_systemic']) {
        $hasPattern = true;
        echo "SYSTEMIC PATTERN DETECTED: {$patternName}\n";
    }
}

// If pattern detected, file complaint
if ($hasPattern || $analysis['severity_score'] >= 80) {
    // Generate complaint to State Attorney
    $complaint = $module->generateComplaint($caseId, 'state_attorney');

    echo "\nComplaint Generated:\n";
    echo "Type: " . $complaint['complaint_type'] . "\n";
    echo "Recipient: " . $complaint['recipient'] . "\n";
    echo "Address: " . $complaint['recipient_address'] . "\n";
    echo "\nViolations Cited:\n";
    foreach ($complaint['violations'] as $violation) {
        echo "- " . $violation['type'] . ": " . $violation['description'] . "\n";
        echo "  Legal Basis: " . $violation['legal_basis'] . "\n";
    }

    echo "\nRecommended Sanctions:\n";
    foreach ($complaint['recommended_sanctions'] as $sanction) {
        echo "- " . $sanction . "\n";
    }

    // Save complaint to file
    file_put_contents(
        "complaint_{$caseId}.txt",
        $complaint['complaint_text']
    );
}
```

---

### Example 4: Build Appeal with Misconduct Grounds

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;

$module = app(ProsecutorialMisconductModule::class);
$caseId = '123';

// First analyze to confirm misconduct
$analysis = $module->analyzeMisconduct($caseId);

if ($analysis['misconduct_detected']) {
    // Build appeal citing misconduct
    $appeal = $module->buildAppeal($caseId, 'zalba');

    echo "Appeal Type: " . $appeal['appeal_type'] . "\n";
    echo "Filing Deadline: " . $appeal['deadline'] . "\n";
    echo "Filing Court: " . $appeal['filing_court'] . "\n";

    echo "\nGrounds for Appeal:\n";
    foreach ($appeal['grounds'] as $ground) {
        echo "\nGround: " . $ground['ground'] . "\n";
        echo "Legal Basis: " . $ground['legal_basis'] . "\n";
        echo "Description: " . $ground['description'] . "\n";
    }

    echo "\n--- APPEAL TEXT ---\n";
    echo $appeal['appeal_text'] . "\n";

    // Save to file
    file_put_contents("appeal_{$caseId}.txt", $appeal['appeal_text']);
}
```

---

## Best Practices

### 1. Evidence Collection

**Always collect comprehensive evidence before analysis:**

```php
// Good: Thorough evidence collection
$case = LegalCase::with(['documents', 'evidence', 'proceedings'])->find($caseId);

// Bad: Minimal data
$case = LegalCase::find($caseId);
```

**Include all relevant documents:**
- Discovery correspondence
- Evidence disclosure statements
- Court filings with timestamps
- Forensic reports (for backdating detection)
- Witness statements
- Interrogation reports

---

### 2. Severity Thresholds

Use appropriate severity thresholds for different actions:

```php
// Dismissal motion: severity >= 80
if ($result['severity_score'] >= 80) {
    $motion = $module->generateDismissalMotion($caseId);
}

// Complaint to State Attorney: severity >= 70 OR pattern detected
if ($result['severity_score'] >= 70 || $hasSystemicPattern) {
    $complaint = $module->generateComplaint($caseId, 'state_attorney');
}

// Judicial Council complaint: critical violations (>= 90)
if ($result['severity_score'] >= 90) {
    $complaint = $module->generateComplaint($caseId, 'judicial_council');
}
```

---

### 3. Document Misconduct Thoroughly

**Always document specific evidence of misconduct:**

```php
foreach ($result['instances'] as $violation) {
    // Log each violation with evidence
    Log::info('Misconduct detected', [
        'case_id' => $caseId,
        'type' => $violation['type'],
        'severity' => $violation['severity'],
        'evidence' => $violation['evidence'],
        'legal_basis' => $violation['legal_basis'],
    ]);

    // Save to case file
    Document::create([
        'legal_case_id' => $caseId,
        'title' => "Misconduct Documentation - {$violation['type']}",
        'content' => json_encode($violation, JSON_PRETTY_PRINT),
        'document_type' => 'misconduct_analysis',
    ]);
}
```

---

### 4. Timing is Critical

**File motions/complaints within legal deadlines:**

```php
// Dismissal motion: File within 8 days of discovery
if ($result['misconduct_detected']) {
    $discoveryDate = Carbon::parse($discoveryDate);
    $deadline = $discoveryDate->addDays(8);

    if (Carbon::now()->lte($deadline)) {
        $motion = $module->generateDismissalMotion($caseId);
        // File immediately
    } else {
        // Too late for dismissal, consider appeal
        Log::warning('Dismissal deadline passed', [
            'case_id' => $caseId,
            'discovery_date' => $discoveryDate,
            'deadline' => $deadline,
        ]);
    }
}

// Appeal: 15 days from judgment
$appeal = $module->buildAppeal($caseId, 'zalba');
// appeal['deadline'] contains calculated deadline
```

---

### 5. Combine with Evidence Analysis

**Integrate with Evidence Recontextualization Module for comprehensive defense:**

```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;
use App\Modules\Evidence\EvidenceAnalysisModule;

$misconductModule = app(ProsecutorialMisconductModule::class);
$evidenceModule = app(EvidenceAnalysisModule::class);

// Analyze misconduct
$misconduct = $misconductModule->analyzeMisconduct($caseId);

// Analyze evidence for selective presentation
$evidence = [
    'id' => 'ev1',
    'type' => 'communication',
    'description' => 'SMS messages',
    'prosecution_description' => 'Partial excerpt',
    'full_content' => 'Full conversation',
];

$recontextualization = $evidenceModule->recontextualizeEvidence($caseId, $evidence);

// Combined defense strategy
if ($misconduct['misconduct_detected'] &&
    $recontextualization['recontextualization']['recontextualization_needed']) {

    echo "POWERFUL DEFENSE STRATEGY AVAILABLE:\n";
    echo "1. Prosecutorial Misconduct: " . $misconduct['severity_level'] . "\n";
    echo "2. Evidence Recontextualization: Credibility " .
         $recontextualization['recontextualization']['credibility_score'] . "\n";

    // Generate dismissal motion citing both
    $motion = $misconductModule->generateDismissalMotion($caseId);
}
```

---

### 6. Prioritize Actions by Urgency

**Use recommended_actions to prioritize:**

```php
$analysis = $module->analyzeMisconduct($caseId);

// Sort actions by priority
$urgentActions = array_filter(
    $analysis['recommended_actions'],
    fn($action) => $action['priority'] === 'urgent'
);

$highActions = array_filter(
    $analysis['recommended_actions'],
    fn($action) => $action['priority'] === 'high'
);

// Execute urgent actions first
foreach ($urgentActions as $action) {
    echo "URGENT: " . $action['description'] . "\n";
    // Execute immediately
}

// Schedule high priority actions
foreach ($highActions as $action) {
    echo "HIGH PRIORITY: " . $action['description'] . "\n";
    // Schedule for soon
}
```

---

## Testing

### Unit Tests

**File**: `tests/Feature/MisconductModuleTest.php`

Run misconduct module tests:

```bash
# Run all misconduct tests
php artisan test --filter=MisconductModuleTest

# Run specific test
php artisan test --filter=test_analyze_misconduct_endpoint
```

**Test Coverage**:
- ✅ Misconduct detection (all 6 types)
- ✅ Severity calculation
- ✅ Pattern analysis
- ✅ Dismissal motion generation
- ✅ Complaint generation
- ✅ Appeal building
- ✅ API endpoint validation
- ✅ JSON structure validation

---

### Integration Tests

**File**: `tests/Feature/MisconductEvidenceIntegrationTest.php`

Run integration tests:

```bash
# Run all integration tests
php artisan test --filter=MisconductEvidenceIntegrationTest

# Run specific scenario
php artisan test --filter=hidden_evidence_detected_and_recontextualized
php artisan test --filter=backdated_document_triggers_dismissal_and_appeal
php artisan test --filter=pattern_of_violations_triggers_complaint
```

**Integration Scenarios**:
- ✅ Hidden evidence + recontextualization
- ✅ Backdated documents + dismissal/appeal
- ✅ Pattern violations + complaint

---

### Manual Testing

```php
// Create test case
$case = LegalCase::create([
    'title' => 'Test Case',
    'description' => 'Testing misconduct detection',
    'case_type' => 'criminal',
    'status' => 'active',
    'prosecutor' => 'Test Prosecutor',
]);

// Add document with backdating
Document::create([
    'legal_case_id' => $case->id,
    'title' => 'Search Warrant',
    'content' => 'Warrant approved Sept 15',
    'filed_at' => '2024-09-15',
    'created_at' => '2024-10-01', // Actually created later
]);

// Analyze
$module = app(ProsecutorialMisconductModule::class);
$result = $module->analyzeMisconduct($case->id);

// Verify detection
dd($result);
```

---

## Changelog

### Version 1.0 (Sprint 2) - October 2024

**Added**:
- Initial release of Prosecutorial Misconduct Module
- MisconductDetector service with 6 detection types
- MisconductPatternAnalyzer for systemic violations
- DismissalMotionGenerator for Croatian legal motions
- ComplaintGenerator for State Attorney/Judicial Council complaints
- AppealBuilder for appeals citing misconduct
- 4 API endpoints (analyze, dismissal, complaint, appeal)
- Comprehensive test suite
- Integration with Evidence Recontextualization Module

**Features**:
- AI-powered misconduct detection using GPT-4o-mini
- Croatian legal language support
- Proper ZKP and Ustav RH citations
- Severity scoring (0-100)
- Pattern analysis for systemic violations
- Dismissal motion generation (severity >= 80)
- Complaint generation for ethics violations
- Appeal building with misconduct grounds

**Legal Framework**:
- ZKP (Zakon o kaznenom postupku) compliance
- Ustav RH (Croatian Constitution) alignment
- Zakon o Državnom odvjetništvu citations
- Professional ethics code integration

---

## Support and Contact

For questions, issues, or contributions related to the Prosecutorial Misconduct Module, please refer to the main project repository.

**Legal Disclaimer**: This software is provided for legal defense purposes only. Users are responsible for ensuring ethical use in compliance with Croatian legal ethics and professional responsibility standards. Always consult with a licensed Croatian attorney before filing motions or complaints.

---

## Related Documentation

- [Evidence Recontextualization Module](./EVIDENCE_RECONTEXTUALIZATION.md) - Countering selective evidence presentation
- [Sprint 3 Technical Documentation](./sprint-3-evidence-recontextualization.md) - Technical architecture
- [Integration Testing Guide](../tests/Feature/MisconductEvidenceIntegrationTest.php) - Testing both modules together

---

**End of Prosecutorial Misconduct Module Documentation**
