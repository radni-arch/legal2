# Legal Artillery API Reference

Programmatic API for legal document generation, response handling, e-komunikacija submission, and digital signing.

## LegalArtilleryAgent

**Class:** `App\Agents\LegalArtilleryAgent`
**Contract:** `App\Agents\Contracts\LegalArtilleryAgentContract`

The main facade for legal document generation. Combines the recursive improvement orchestrator with DOCX rendering and multi-channel output (Gmail, e-komunikacija).

### fire()

Generate a single legal document for a given profile.

```php
use App\Agents\LegalArtilleryAgent;

$agent = app(LegalArtilleryAgent::class);

$run = $agent->fire(
    profileKey: 'predsjednik_suda',
    userId: Auth::id(),
    additionalContext: ['extra_info' => 'Additional case details'],
    sendEmail: true,
    asDraft: false,
    toEmail: 'sud@example.com',
    maxIterations: 5,
    submitEkom: false,
);

// Access results
$run->id;               // UUID of the generation run
$run->status;           // 'completed' | 'failed' | 'running'
$run->final_document;   // Final generated document text
$run->final_score;      // Weighted quality score (0-100)
$run->total_iterations; // Number of Worker/Critic iterations
$run->stopped_reason;   // 'converged' | 'max_iterations' | 'error'
$run->iterations;       // Collection of DocumentIteration records
$run->context;          // DocumentContext with assembled legal context
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `profileKey` | `string` | _(required)_ | Profile key from config (e.g. `'predsjednik_suda'`) |
| `userId` | `int` | _(required)_ | ID of the user initiating generation |
| `additionalContext` | `array` | `[]` | Extra context injected into generation |
| `sendEmail` | `bool` | `false` | Send result via Gmail |
| `asDraft` | `bool` | `false` | Save as Gmail draft instead of sending |
| `toEmail` | `?string` | `null` | Override recipient email address |
| `maxIterations` | `?int` | `null` | Override max iteration count (default from config) |
| `submitEkom` | `bool` | `false` | Submit via e-komunikacija after generation |

**Returns:** `DocumentGenerationRun` — The completed generation run with all iterations and context.

### barrage()

Fire multiple profiles in sequence. By default, fires all profiles with `immediate` priority.

```php
$results = $agent->barrage(
    profileKeys: ['predsjednik_suda', 'ombudsman', 'dorh_production'],
    userId: Auth::id(),
    sendEmail: true,
    asDraft: true,
);

// Results keyed by profile
foreach ($results as $profileKey => $run) {
    if ($run instanceof DocumentGenerationRun) {
        echo "{$profileKey}: Score {$run->final_score}\n";
    } else {
        // Error case
        echo "{$profileKey}: Error - {$run['error']}\n";
    }
}
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `profileKeys` | `?array` | `null` | Profile keys to fire; `null` = all immediate-priority |
| `userId` | `int` | `1` | ID of the user initiating generation |
| `sendEmail` | `bool` | `false` | Send results via Gmail |
| `asDraft` | `bool` | `true` | Save as Gmail drafts |

**Returns:** `array<string, DocumentGenerationRun|array>` — Results keyed by profile key. Failed profiles return an error array.

### availableProfiles()

List all available document profiles with metadata.

```php
$profiles = $agent->availableProfiles();

// Returns:
// [
//     'predsjednik_suda' => [
//         'name' => 'Zahtjev predsjedniku suda za uvid u spis',
//         'forum' => 'Opcinski sud u Osijeku',
//         'priority' => 'immediate',
//         'legal_basis' => ['PZ cl.150 st.1 — opravdani interes', ...],
//     ],
//     ...
// ]
```

### getRun()

Retrieve a generation run by ID with its iterations and context.

```php
$run = $agent->getRun('uuid-of-run');

if ($run) {
    echo "Status: {$run->status}";
    echo "Iterations: {$run->total_iterations}";
    echo "Score: {$run->final_score}";
}
```

---

## ResponseHandler

**Class:** `App\Services\LegalArtillery\ResponseHandler`

Handles opponent response parsing and counter-document generation.

### parseResponse()

Parse an uploaded opponent response document and extract structured arguments.

```php
use App\Services\LegalArtillery\ResponseHandler;

$handler = app(ResponseHandler::class);

$parsed = $handler->parseResponse(
    file: $uploadedFile,           // UploadedFile instance
    extractedContent: $textContent, // Extracted text from the document
    originalProfileKey: 'predsjednik_suda',
    runId: $originalRun->id,       // Optional: link to original generation run
);

// OpponentResponse DTO
$parsed->summary;             // Summary of opponent's position
$parsed->keyArguments;        // Array of identified arguments with legal basis
$parsed->weaknesses;          // Identified weaknesses in their argumentation
$parsed->recommendedCounters; // Recommended counter-arguments
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `file` | `UploadedFile` | The uploaded response document |
| `extractedContent` | `string` | Text content extracted from the document |
| `originalProfileKey` | `string` | Profile key of the original document sent |
| `runId` | `?string` | Optional generation run ID for linking |

**Returns:** `OpponentResponse` DTO with parsed arguments, weaknesses, and counter-recommendations.

### parseFromUrl()

Parse an opponent response from a URL source (e.g. e-komunikacija document link).

```php
$parsed = $handler->parseFromUrl(
    url: 'https://ekomunikacija.example/document/123',
    extractedContent: $textContent,
    originalProfileKey: 'predsjednik_suda',
    runId: $originalRun->id,
);
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `url` | `string` | Source URL of the opponent response |
| `extractedContent` | `string` | Text content extracted from the URL document |
| `originalProfileKey` | `string` | Profile key of the original document sent |
| `runId` | `?string` | Optional generation run ID for linking |

**Returns:** `OpponentResponse` DTO.

### generateCounterDocument()

Generate a counter-response document targeting the opponent's arguments.

```php
$counterText = $handler->generateCounterDocument(
    response: $parsed,
    originalProfileKey: 'predsjednik_suda',
    counterProfileKey: 'ponovljeni_zahtjev',
);

// $counterText contains the full counter-document text
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `response` | `OpponentResponse` | The parsed opponent response |
| `originalProfileKey` | `string` | Profile of the original document |
| `counterProfileKey` | `string` | Profile to use for the counter-document |

**Returns:** `string` — Generated counter-document text.

### Full Workflow Example

```php
use App\Services\LegalArtillery\ResponseHandler;

$handler = app(ResponseHandler::class);

// 1. Parse the opponent's response
$parsed = $handler->parseResponse($file, $content, 'predsjednik_suda');

// 2. Review what was found
echo "Summary: {$parsed->summary}\n";
echo "Arguments: " . count($parsed->keyArguments) . "\n";
echo "Weaknesses: " . count($parsed->weaknesses) . "\n";

// 3. Generate counter-document
$counter = $handler->generateCounterDocument(
    $parsed,
    'predsjednik_suda',
    'ponovljeni_zahtjev'
);
```

---

## EKomunikacijaDispatcher

**Class:** `App\Services\LegalArtillery\EKomunikacijaDispatcher`

Submits legal documents to courts via the Croatian e-komunikacija system.

### submit()

Submit a document to e-komunikacija.

```php
use App\Services\LegalArtillery\EKomunikacijaDispatcher;
use App\DTOs\DocumentProfile;
use App\DTOs\CaseContext;

$dispatcher = app(EKomunikacijaDispatcher::class);

$result = $dispatcher->submit(
    profile: DocumentProfile::fromConfig('predsjednik_suda'),
    context: CaseContext::fromConfig(),
    docxPath: '/path/to/document.docx',
    additionalAttachments: [
        ['path' => '/path/to/evidence.pdf', 'filename' => 'evidence.pdf'],
    ],
);

// Result array
$result['success'];       // bool
$result['submission_id']; // Submission tracking ID (on success)
$result['error'];         // Error message (on failure)
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `profile` | `DocumentProfile` | Document profile defining recipient and type |
| `context` | `CaseContext` | Case context with case number and sender info |
| `docxPath` | `string` | Absolute path to the DOCX document |
| `additionalAttachments` | `array` | Extra attachments with `path`, optional `filename` and `mime_type` |

**Returns:** `array` with `success`, `submission_id` (on success), or `error` (on failure).

### preparePayload()

Prepare the submission payload without actually submitting. Useful for inspection and testing.

```php
$payload = $dispatcher->preparePayload($profile, $context, $docxPath);

// Inspect payload structure:
// [
//     'case_number' => 'Pp Prz-74/2025',
//     'court_id' => 'OS_OSIJEK',
//     'document_type' => 'ZAHTJEV',
//     'sender' => ['name' => ..., 'oib' => ..., 'address' => ..., 'email' => ...],
//     'subject' => 'Zahtjev predsjedniku suda za uvid u spis',
//     'description' => 'Podnesak u predmetu Pp Prz-74/2025',
//     'attachments' => [...],
//     'metadata' => ['generated_by' => 'legal_artillery', ...],
// ]
```

### checkStatus()

Check the status of a previous submission.

```php
$status = $dispatcher->checkStatus('submission-id-123');
```

### getHistory()

Get submission history for a case number.

```php
$history = $dispatcher->getHistory('Pp Prz-74/2025');
```

### Court ID Mapping

The dispatcher automatically maps profile recipient institutions to court identifiers:

| Institution | Court ID |
|------------|----------|
| Opcinski sud u Osijeku | `OS_OSIJEK` |
| Zupanijski sud u Osijeku | `ZS_OSIJEK` |
| Ustavni sud RH | `USRH` |
| Vrhovni sud Republike Hrvatske | `VSRH` |
| Ministarstvo pravosudja i uprave | `MPU` |
| ECHR / ESLJP | `ECHR` |
| Drzavno odvjetnistvo | `DORH` |

### Document Type Mapping

| Profile Key | e-komunikacija Type |
|------------|---------------------|
| `predsjednik_suda` | `ZAHTJEV` |
| `dorh_production` | `ZAHTJEV_DORH` |
| `kazneni_sud_motion` | `PRIJEDLOG` |
| `izdvajanje_dokaza` | `PRIJEDLOG_IZDVAJANJE` |
| `ustavni_sud` | `USTAVNA_TUZBA` |
| `ombudsman` | `PRITUZBA` |
| `ministarstvo_pravosudja` | `PRITUZBA_NADZOR` |
| `echr_application` | `ECHR_APPLICATION` |

---

## DigitalSigner

**Class:** `App\Services\LegalArtillery\DigitalSigner`

Signs PDF documents using the Croatian electronic ID card (eOI) via PKCS#11.

### Requirements

- OpenSC library installed (`opensc-pkcs11.so`)
- Compatible smart card reader connected
- Croatian eID card inserted
- LibreOffice installed (for DOCX to PDF conversion)

### signDocument()

Full workflow: convert DOCX to PDF, then sign it.

```php
use App\Services\LegalArtillery\DigitalSigner;

$signer = app(DigitalSigner::class);

$result = $signer->signDocument(
    docxPath: '/path/to/document.docx',
    pin: '1234', // Card PIN (or set in config)
);

$result['success'];     // bool
$result['signed_pdf'];  // Path to signed PDF
$result['signature'];   // Base64-encoded signature
$result['timestamp'];   // ISO-8601 signing timestamp
$result['certificate']; // Certificate info from the card
```

### signPdf()

Sign an existing PDF document.

```php
$result = $signer->signPdf(
    pdfPath: '/path/to/document.pdf',
    pin: '1234',
);
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `pdfPath` | `string` | Absolute path to the PDF file |
| `pin` | `?string` | Card PIN; falls back to config `digital-signature.pkcs11.pin` |

**Returns:** `array` with `success`, `signed_pdf`, `signature`, `timestamp`, `certificate`.

**Throws:** `RuntimeException` if PIN is missing, card reader unavailable, or signing fails.

### convertToPdf()

Convert a DOCX file to PDF using LibreOffice in headless mode.

```php
$pdfPath = $signer->convertToPdf('/path/to/document.docx');
// Returns: '/path/to/document.pdf'
```

### preparePdfForSigning()

Prepare a PDF for signing by computing its hash.

```php
$prepared = $signer->preparePdfForSigning('/path/to/document.pdf');

// [
//     'pdf_path' => '/path/to/document.pdf',
//     'hash' => 'sha256-hex-hash',
//     'hash_algorithm' => 'SHA-256',
//     'signature_field' => 'LegalArtillerySignature',
//     'file_size' => 12345,
// ]
```

### isCardReaderAvailable()

Check if a PKCS#11 card reader and card are available.

```php
if ($signer->isCardReaderAvailable()) {
    // Card reader and card detected
}
```

### getCertificateInfo()

Get certificate information from the inserted card.

```php
$cert = $signer->getCertificateInfo();

// [
//     'label' => 'Certificate label',
//     'subject' => 'CN=Name, ...',
//     'available' => true,
// ]
```

---

## LegalArtilleryOrchestrator

**Class:** `App\Agents\LegalArtilleryOrchestrator`
**Extends:** `Vizra\VizraADK\Agents\BaseLlmAgent`

The core recursive generation engine. Typically accessed via `LegalArtilleryAgent` facade, but can be used directly for Vizra ADK integration.

### Vizra ADK Integration

```php
use Vizra\VizraADK\System\AgentContext;

$orchestrator = app(LegalArtilleryOrchestrator::class);

// Via Vizra ADK execute()
$result = $orchestrator->execute(
    input: [
        'profile_key' => 'predsjednik_suda',
        'user_id' => 1,
        'max_iterations' => 5,
        'additional_context' => [],
    ],
    context: new AgentContext(),
);

// Returns array:
// [
//     'run_id' => 'uuid',
//     'status' => 'completed',
//     'final_document' => '...',
//     'final_score' => 85.5,
//     'total_iterations' => 3,
//     'stopped_reason' => 'converged',
// ]
```

### getConfig()

Get agent configuration for ADK registry.

```php
$config = $orchestrator->getConfig();

// [
//     'model' => 'claude-sonnet-4-20250514',
//     'max_tokens' => 8192,
//     'max_iterations' => 10,
//     'convergence_threshold' => 5.0,
//     'supported_profiles' => ['predsjednik_suda', 'dorh_production', ...],
// ]
```

### Scoring Weights

The Critic evaluates documents using these default weights (configurable via `documents.scoring_weights`):

| Dimension | Weight | Description |
|-----------|--------|-------------|
| `legal_rigor` | 0.40 | Accuracy of legal citations (ZKP, Ustav, PZ) |
| `persuasiveness` | 0.25 | Strength of arguments and logical flow |
| `clarity` | 0.20 | Readability, organization, structure |
| `evidence_integration` | 0.10 | Integration of supporting evidence |
| `formatting` | 0.05 | Adherence to Croatian legal format |

### Stopping Conditions

The recursive loop stops when:
1. **Convergence** — Improvement delta falls below threshold (default: 5.0%)
2. **Max iterations** — Iteration count reaches the configured maximum (default: 10)
3. **Error** — An unrecoverable error occurs during generation

---

## Models

### DocumentGenerationRun

Represents a single generation run with all its iterations and context.

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `document_type` | string | Profile key used for generation |
| `status` | string | `running`, `completed`, `failed` |
| `user_id` | int | User who initiated the run |
| `final_document` | text | Final generated document content |
| `final_score` | float | Final weighted quality score |
| `total_iterations` | int | Number of Worker/Critic iterations completed |
| `stopped_reason` | string | `converged`, `max_iterations`, `error` |
| `model_config` | json | LLM model, iterations config, output paths |

**Relationships:**
- `iterations` — `HasMany` DocumentIteration
- `context` — `HasOne` DocumentContext

### DocumentIteration

A single Worker or Critic iteration within a generation run.

| Field | Type | Description |
|-------|------|-------------|
| `generation_run_id` | UUID | Parent generation run |
| `iteration_number` | int | Iteration sequence number |
| `phase` | string | `worker` or `critic` |
| `document_version` | text | Generated document text (worker phase) |
| `critic_feedback` | json | Scores and feedback (critic phase) |
| `scores` | json | Individual dimension scores |
| `weighted_score` | float | Calculated weighted score |
| `improvement_delta` | float | Percentage improvement from previous iteration |

### DocumentContext

Assembled context used for a generation run.

| Field | Type | Description |
|-------|------|-------------|
| `generation_run_id` | UUID | Parent generation run |
| `context_type` | string | Always `legal_artillery` |
| `raw_input` | json | Original input (profile, case context, additional) |
| `assembled_context` | json | Full assembled context sent to LLM |
