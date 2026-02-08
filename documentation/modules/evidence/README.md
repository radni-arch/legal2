# EvidenceAnalysisModule - Croatian Legal System

## Overview

The **EvidenceAnalysisModule** provides comprehensive evidence analysis for the Croatian legal system, focused on identifying legitimate grounds for challenging evidence admissibility under:

- **Zakon o kaznenom postupku (ZKP)** - Criminal Procedure Act
- **Ustav Republike Hrvatske** - Croatian Constitution

## ⚖️ Ethical Use Statement

This module is designed for **LEGITIMATE DEFENSE** purposes only:

### ✅ Ethical Uses:
- Identifying constitutional violations (Ustav RH)
- Detecting procedural errors in evidence collection
- Finding grounds for evidence exclusion under ZKP
- Providing alternative interpretations based on actual facts
- Challenging witness credibility on valid grounds
- Identifying timeline inconsistencies

### ❌ NOT FOR:
- Fabricating evidence
- Distorting clear facts
- Creating false narratives
- Obstructing justice
- Manufacturing witnesses
- Evidence tampering

---

## Features

### 1. **Comprehensive Evidence Analysis**
Analyzes evidence for:
- **Admissibility** under ZKP
- **Constitutional violations** under Ustav RH
- **Alternative interpretations** (legitimate, fact-based)
- **Suppression grounds**
- **Excludability score** (0-100)

### 2. **Evidence Admissibility Checker (ZKP)**

Checks compliance with:

| ZKP Article | Check | Description |
|-------------|-------|-------------|
| Članak 9 | Lawfulness | Evidence obtained legally |
| Članak 10 | No Coercion | Prohibition of torture/coercion |
| Članak 11 | Chain of Custody | Proper evidence handling |
| Članak 292 | Relevance | Evidence is relevant and probative |
| Članak 293 | Authentication | Proper evidence authentication |
| Članak 405 | Procedural Compliance | Proper procedures followed |

### 3. **Constitutional Violation Detector (Ustav RH)**

Detects violations of:

| Ustav RH Article | Right | Violation Examples |
|------------------|-------|-------------------|
| Članak 23 | Prohibition of Torture | Evidence via torture/coercion |
| Članak 28 | Presumption of Innocence | Presumption of guilt |
| Članak 29 | Fair Trial & Right to Defense | No lawyer present, evidence not disclosed |
| Članak 32 | Freedom of Movement | Unlawful detention |
| Članak 34 | Home Inviolability | Warrantless home search |
| Članak 35 | Privacy & Communications | Unlawful wiretapping, email access |

### 4. **Alternative Interpretation Analyzer**

Provides legitimate alternative interpretations:
- Multiple valid interpretations of ambiguous evidence
- Competing expert opinions (scientifically valid)
- Timeline inconsistencies
- Witness credibility issues
- Based on actual facts (not fabrication)

### 5. **Suppression Motion Generator**

Generates formal Croatian court motions:
- **"Prijedlog za isključenje dokaza"**
- Proper legal format and citations
- Filing instructions
- Legal authorities (ZKP & Ustav RH)

---

## API Endpoints

### 1. Analyze Evidence

**POST** `/api/evidence/analyze/{caseId}`

Comprehensive evidence analysis.

**Request:**
```json
{
  "evidence": [
    {
      "id": "ev1",
      "type": "physical",
      "description": "Stolen items",
      "collection_method": "warrantless search",
      "collection_location": "defendant home",
      "chain_of_custody": []
    },
    {
      "id": "ev2",
      "type": "testimonial",
      "description": "Witness statement",
      "lawyer_present": false,
      "informed_of_rights": false
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "case_id": "123",
    "total_evidence_items": 2,
    "highly_challengeable": 2,
    "evidence_analysis": [
      {
        "evidence_id": "ev1",
        "description": "Stolen items",
        "type": "physical",
        "admissibility": {
          "admissible": false,
          "confidence": 85,
          "issues": [
            {
              "check": "lawfulness",
              "description": "Possible lack of court warrant",
              "legal_basis": "ZKP Članak 9",
              "severity": 80
            }
          ]
        },
        "constitutional_issues": [
          {
            "article": "Ustav RH Članak 34",
            "violation": "Nepovredi​vost doma - Home search without warrant",
            "severity": 95,
            "remedy": "Mandatory suppression"
          }
        ],
        "alternative_interpretations": [...],
        "suppression_grounds": [...],
        "challenge_strategy": {
          "primary_approach": "Constitutional challenge",
          "success_probability": 70
        },
        "excludability_score": 95
      }
    ],
    "summary": "...",
    "recommended_motions": [...]
  }
}
```

### 2. Generate Suppression Motion

**POST** `/api/evidence/suppress-motion/{caseId}`

Generate "Prijedlog za isključenje dokaza".

**Request:**
```json
{
  "evidence_ids": ["ev1", "ev2"]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "motion_type": "Prijedlog za isključenje dokaza",
    "case_id": "123",
    "evidence_ids": ["ev1", "ev2"],
    "primary_grounds": [...],
    "legal_authorities": {
      "statutes": ["ZKP Članak 11"],
      "constitution": ["Ustav RH Članak 34", "Ustav RH Članak 29"]
    },
    "motion_text": "PRIJEDLOG ZA ISKLJUČENJE DOKAZA\n\n...",
    "filing_instructions": {
      "court": "Županijski sud",
      "filing_method": "E-Opis (electronic) ili dostava putem odvjetnika",
      "deadline": "Before trial begins",
      "service": "Serve copy on Državno odvjetništvo"
    }
  }
}
```

### 3. Check Admissibility

**POST** `/api/evidence/check-admissibility/{caseId}`

Check single evidence item admissibility under ZKP.

### 4. Detect Constitutional Violations

**POST** `/api/evidence/constitutional-violations/{caseId}`

Detect violations of Ustav RH.

### 5. Get Alternative Interpretations

**POST** `/api/evidence/alternative-interpretations/{caseId}`

Get legitimate alternative interpretations of evidence.

---

## Usage Examples

### Example 1: Analyze Evidence Collection

```php
use App\Modules\Evidence\EvidenceAnalysisModule;

$evidence = [
    [
        'id' => 'ev1',
        'type' => 'physical',
        'description' => 'Narcotics found in car',
        'collection_method' => 'traffic stop search',
        'warrant' => false,
        'probable_cause' => 'officer observed suspicious behavior',
    ],
];

$module = app(EvidenceAnalysisModule::class);
$result = $module->analyzeEvidence($caseId, $evidence);

if ($result['highly_challengeable'] > 0) {
    echo "Evidence can be challenged!\n";
    echo "Excludability Score: " . $result['evidence_analysis'][0]['excludability_score'];
}
```

### Example 2: Constitutional Challenge

```php
$evidence = [
    'type' => 'digital',
    'description' => 'Email messages',
    'collection_method' => 'ISP data request without warrant',
    'court_order' => false,
];

$violations = $module->detectConstitutionalViolations($evidence, $caseId);

foreach ($violations as $violation) {
    if ($violation['severity'] >= 80) {
        echo "SERIOUS VIOLATION: {$violation['article']}\n";
        echo "Remedy: {$violation['remedy']}\n";
    }
}
```

### Example 3: Generate Suppression Motion

```php
// After identifying excludable evidence
$evidenceIds = ['ev1', 'ev2', 'ev3'];

$motion = $module->generateSuppressionMotion($caseId, $evidenceIds);

// Get the motion text in Croatian
echo $motion['motion_text'];

// File with court
file_put_contents('prijedlog_za_iskljucenje_dokaza.pdf', $motion['motion_text']);
```

### Example 4: Alternative Interpretations

```php
$evidence = [
    'type' => 'forensic',
    'description' => 'DNA evidence found at scene',
    'prosecution_interpretation' => 'Defendant was present at crime scene',
];

$interpretations = $module->getAlternativeInterpretations($evidence, $caseId);

foreach ($interpretations as $alt) {
    if ($alt['credibility_score'] >= 60) {
        echo "Credible Alternative: {$alt['explanation']}\n";
        echo "Supporting Evidence Needed: {$alt['supporting_evidence']}\n";
    }
}
```

---

## Excludability Scoring

### Score Calculation

```
excludability_score = 0

// Constitutional violations (highest weight)
+ (count(constitutional_violations) × 25)

// Not admissible under ZKP
+ 30 (if not admissible)

// Suppression grounds
+ (count(suppression_grounds) × 15)

= min(100, total_score)
```

### Score Ranges

| Score | Category | Action |
|-------|----------|--------|
| 80-100 | Highly Challengeable | Strong grounds for exclusion |
| 60-79 | Challengeable | Moderate grounds, worth pursuing |
| 40-59 | Possibly Challengeable | Weak grounds, low success probability |
| 0-39 | Not Challengeable | Focus on alternative interpretations |

---

## Croatian Legal Framework

### ZKP (Criminal Procedure Act)

**Key Articles:**

- **Članak 9** - Zakonitost dokaznih radnji (Lawfulness of evidence collection)
- **Članak 10** - Zabrana mučenja (Prohibition of torture)
- **Članak 11** - Isključenje nezakonitih dokaza (Exclusion of illegal evidence)
- **Članak 236** - Upozorenje svjedoka (Warning to witnesses)
- **Članak 237** - Saslušanje osumnjičenika (Questioning of suspect)
- **Članak 292** - Uvjeti dopuštenosti dokaza (Admissibility requirements)
- **Članak 293** - Posebna pravila o dokazima (Special rules on evidence)

### Ustav RH (Croatian Constitution)

**Key Articles:**

- **Članak 23** - Zabrana mučenja i nečovječnog postupanja
- **Članak 28** - Presumpcija nevinosti
- **Članak 29** - Pravo na pravično suđenje i obranu
- **Članak 32** - Sloboda kretanja
- **Članak 34** - Nepovredivost doma
- **Članak 35** - Tajnost komunikacija

---

## Components

### 1. EvidenceAnalysisModule
- **Location:** `app/Modules/Evidence/EvidenceAnalysisModule.php`
- **Purpose:** Main entry point for evidence analysis

### 2. EvidenceAdmissibilityChecker
- **Location:** `app/Modules/Evidence/Services/EvidenceAdmissibilityChecker.php`
- **Purpose:** Checks ZKP compliance

### 3. ConstitutionalViolationDetector
- **Location:** `app/Modules/Evidence/Services/ConstitutionalViolationDetector.php`
- **Purpose:** Detects Ustav RH violations

### 4. AlternativeInterpretationAnalyzer
- **Location:** `app/Modules/Evidence/Services/AlternativeInterpretationAnalyzer.php`
- **Purpose:** Provides legitimate alternative interpretations

### 5. SuppressionMotionGenerator
- **Location:** `app/Modules/Evidence/Services/SuppressionMotionGenerator.php`
- **Purpose:** Generates Croatian court motions

---

## Best Practices

### 1. Always Document Chain of Custody
```php
$evidence['chain_of_custody'] = [
    ['handler' => 'Officer Marić', 'timestamp' => '2025-01-15 14:30', 'action' => 'Collected at scene'],
    ['handler' => 'Evidence Tech Kovač', 'timestamp' => '2025-01-15 16:00', 'action' => 'Logged into evidence room'],
];
```

### 2. Flag Warrantless Searches
```php
if (!isset($evidence['warrant']) && $evidence['type'] === 'search') {
    // Will be flagged for constitutional challenge
}
```

### 3. Note Rights Warnings
```php
$evidence['informed_of_rights'] = true;
$evidence['rights_warned'] = ['right to remain silent', 'right to lawyer'];
```

### 4. Prioritize High-Severity Violations
```php
$highSeverity = array_filter($violations, fn($v) => $v['severity'] >= 80);
// These have best chance of suppression
```

---

## Performance

- **Analysis Time:** ~3-5 seconds per evidence item
- **API Costs:** ~$0.05-0.15 per analysis (GPT-4o usage)
- **Caching:** Results cached for 24 hours

---

## Testing

```bash
php artisan test --filter=EvidenceModuleTest
```

---

## Changelog

- **v1.0.0** (2025-10-28): Initial release
  - ZKP admissibility checking
  - Ustav RH violation detection
  - Alternative interpretation analysis
  - Suppression motion generation
  - Croatian legal format compliance

---

## Legal Disclaimer

This module provides **analysis tools** for defense attorneys. It does NOT:
- Provide legal advice
- Guarantee case outcomes
- Replace qualified legal counsel
- Authorize unethical practices

Always consult with a licensed Croatian attorney before using analysis results in actual legal proceedings.

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review ZKP and Ustav RH documentation
- Consult with legal experts for interpretation
