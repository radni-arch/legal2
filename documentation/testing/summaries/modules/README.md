# Modules Test Summaries

Test documentation for domain-specific legal defense modules.

---

## Components Covered

### Evidence Module
**Namespace**: `app/Modules/Evidence/`
**Test File**: `tests/Feature/EvidenceModuleTest.php`

Comprehensive evidence analysis and defense capabilities:
- Evidence recontextualization
- Selective presentation detection
- Admissibility checking
- Constitutional violation detection
- Suppression motion generation

**Key Services**:
- `EvidenceAnalysisModule` - Main orchestrator
- `ContextAnalyzer` - Detects selective presentation (5 types)
- `RecontextualizationService` - Generates defense narratives
- `AdmissibilityChecker` - Analyzes evidence admissibility
- `ConstitutionalAnalyzer` - Detects rights violations

**Selective Presentation Types**:
1. **Partial Messages** - SMS/email with incriminating excerpt only
2. **Cherry-Picked Timeline** - Specific times shown, exculpatory timeline hidden
3. **Out-of-Context Media** - Photos/videos with misleading framing
4. **Partial Statements** - Witness statements quoted selectively
5. **Selective Records** - Financial/phone records showing only suspicious activity

**Legal Basis**:
- ZKP Članak 9 - Objektivnost (prosecution must present both incriminating AND exculpatory)
- ZKP Članak 331 - Slobodna ocjena dokaza (full context evaluation)
- Ustav RH Članak 29 - Pravo na pravično suđenje (fair trial)

---

### Misconduct Module
**Namespace**: `app/Modules/Misconduct/`
**Test File**: `tests/Feature/MisconductModuleTest.php`

Prosecutorial misconduct detection and response:
- 6 types of misconduct detection
- Brady violation analysis
- Dismissal motion generation
- Ethics complaint generation

**Misconduct Types**:
1. **Brady Violations** - Suppression of exculpatory evidence
2. **Witness Tampering** - Improper witness contact or coaching
3. **Evidence Fabrication** - Creating or altering evidence
4. **Procedural Violations** - Violating defendant rights
5. **Selective Prosecution** - Discriminatory case selection
6. **Trial Misconduct** - Improper arguments or evidence presentation

**Key Services**:
- `ProsecutorialMisconductModule` - Main orchestrator
- `BradyAnalyzer` - Detects exculpatory evidence suppression
- `MisconductDetector` - Multi-type misconduct detection
- `DismissalMotionGenerator` - Generates motions to dismiss
- `EthicsComplaintGenerator` - Generates bar complaints

**Legal Basis**:
- Brady v. Maryland (US precedent, influential in Croatian law)
- ZKP Članak 9 - Objektivnost
- Zakon o Državnom odvjetništvu - State Attorney professional standards

---

### Topics Framework
**Namespace**: `app/Modules/Topics/`
**Test Files**: `tests/Unit/Topics/`

Modular abuse detection framework for specific legal topics:

#### Drug Charge Abuse Detector
**File**: `app/Modules/Topics/Analyzers/DrugChargeAbuseDetector.php`
**Test File**: `tests/Unit/Topics/DrugChargeAbuseDetectorTest.php`

Detects prosecutorial overcharging in drug cases:
- Personal use vs. distribution
- Quantity analysis vs. statutory thresholds
- Intent evidence evaluation
- Regional comparison (Osijek vs. national averages)

**Detection Criteria**:
- Quantity within personal use range (< 1g cocaine, < 5g marijuana)
- No distribution evidence (scales, packaging, multiple buyers)
- No intent to distribute (single-use paraphernalia only)
- Charged as distribution despite lack of evidence

---

#### Home Search Abuse Detector
**File**: `app/Modules/Topics/Analyzers/HomeSearchWarrantAbuseDetector.php`
**Test File**: `tests/Unit/Topics/HomeSearchWarrantAbuseDetectorTest.php`

Detects disproportionate home search warrants:
- Warrant justification analysis
- Proportionality assessment
- Alternative methods evaluation
- Privacy rights balance

**Detection Criteria**:
- Minor offense (< 3 years max sentence)
- No flight risk or evidence destruction risk
- Alternative methods available (voluntary surrender, public meeting)
- Disproportionate intrusion on privacy

**Legal Basis**:
- Ustav RH Članak 34 - Nepovredivost stana (home inviolability)
- ZKP Članak 213 - Pretres stana (home search requirements)

---

### HomeSearch Module
**Namespace**: `app/Modules/HomeSearch/`

Specialized module for home search warrant analysis (builds on Topics framework):
- Constitutional analysis
- Proportionality scoring
- Alternative methods suggestions
- Challenge strategy generation

---

### Defence Module
**Namespace**: `app/Modules/Defence/`

Defense strategy analysis and recommendations:
- Case strength assessment
- Defense theory generation
- Witness strategy
- Motion recommendations

---

## Test Coverage Areas

### Unit Tests

**Module Initialization**:
- ✅ Service container binding
- ✅ Configuration loading
- ✅ Dependency injection

**Analysis Accuracy**:
- ✅ Selective presentation detection (85%+ accuracy)
- ✅ Misconduct type classification
- ✅ Abuse pattern recognition
- ✅ Legal basis citation

**Scoring Algorithms**:
- ✅ Credibility scores (0-100)
- ✅ Severity scores (0-100)
- ✅ Confidence scores (0-100)
- ✅ Proportionality scores (0-100)

**Document Generation**:
- ✅ Suppression motions (Croatian legal format)
- ✅ Dismissal motions (with proper citations)
- ✅ Ethics complaints (bar association format)
- ✅ Defense narratives (persuasive, evidence-based)

---

### Integration Tests

**End-to-End Workflows**:
- ✅ Evidence → Analysis → Recontextualization → Motion
- ✅ Case → Misconduct Detection → Dismissal Motion
- ✅ Drug Charge → Abuse Detection → Statistics → Defense Strategy

**API Endpoints**:
- ✅ `POST /api/evidence/recontextualize/{caseId}`
- ✅ `POST /api/evidence/analyze/{caseId}`
- ✅ `POST /api/misconduct/analyze/{caseId}`
- ✅ `POST /api/topics/drug-charges/analyze/{caseId}`

**Real Case Scenarios**:
- ✅ SMS selective presentation (actual case data)
- ✅ Timeline cherry-picking (actual case data)
- ✅ Drug overcharging (actual case data)
- ✅ Home search disproportionality (actual case data)

---

## Running Tests

### All Module Tests
```bash
./scripts/run-tests.sh --filter=ModuleTest
```

### Specific Modules
```bash
# Evidence module
./scripts/run-tests.sh --filter=EvidenceModuleTest

# Misconduct module
./scripts/run-tests.sh --filter=MisconductModuleTest

# Topics framework
./scripts/run-tests.sh --filter=Topics

# Drug charge abuse
./scripts/run-tests.sh --filter=DrugChargeAbuseDetectorTest

# Home search abuse
./scripts/run-tests.sh --filter=HomeSearchWarrantAbuseDetectorTest
```

---

## Test Summaries

Detailed test summaries for modules and analyzers:

- ✅ [Home Search Abuse Detector](home-search-abuse.md) - Disproportionate home search warrant detection
- ✅ [Proportionality Analyzer](proportionality-analyzer.md) - Legal proportionality assessment
- ✅ [Statistical Analyzer](statistical-analyzer.md) - Regional comparison and statistical analysis
- ✅ [Odluke Search Agent](odluke-search-agent.md) - Autonomous court decision discovery agent
- [ ] Evidence module (to be added)
- [ ] Misconduct module (to be added)
- [ ] Topics framework overview (to be added)
- [ ] Drug charge abuse detector (to be added)

---

## Known Issues

*(Document any known issues, flaky tests, or technical debt here)*

---

**Last Updated**: 2025-11-09
