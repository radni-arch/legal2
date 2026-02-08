# Legal Artillery Sprint Remediation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Address the 5 Major and 9 Minor issues identified in the code review to raise the grade from 7.0/10 to 9.0/10.

**Architecture:** Fix dependency injection by creating a service provider, add missing test coverage, improve error handling with custom exceptions, add security sanitization, and complete PHPDoc documentation.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit/Pest, Mockery

---

## Phase 1: Dependency Injection & Service Provider

### Task 1: Create LegalArtilleryServiceProvider

**Files:**
- Create: `app/Providers/LegalArtilleryServiceProvider.php`
- Modify: `bootstrap/providers.php`

**Step 1: Write the service provider**

```php
<?php

namespace App\Providers;

use App\Agents\LegalArtilleryAgent;
use App\Services\LegalArtillery\DocxRenderer;
use App\Services\LegalArtillery\GmailDispatcher;
use App\Services\LegalArtillery\LlmClient;
use App\Services\LegalArtillery\RecursiveDocumentWriter;
use Illuminate\Support\ServiceProvider;

class LegalArtilleryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, fn () => LlmClient::fromConfig());

        $this->app->bind(RecursiveDocumentWriter::class, function ($app) {
            return new RecursiveDocumentWriter($app->make(LlmClient::class));
        });

        $this->app->bind(DocxRenderer::class);
        $this->app->bind(GmailDispatcher::class);

        $this->app->bind(LegalArtilleryAgent::class, function ($app) {
            return new LegalArtilleryAgent($app->make(LlmClient::class));
        });
    }
}
```

**Step 2: Register the provider**

Add to `bootstrap/providers.php`:
```php
App\Providers\LegalArtilleryServiceProvider::class,
```

**Step 3: Run tests to verify no regressions**

```bash
php artisan test tests/Unit/DTOs/ tests/Unit/Agents/
```

**Step 4: Commit**

```bash
git add app/Providers/LegalArtilleryServiceProvider.php bootstrap/providers.php
git commit -m "feat: add LegalArtilleryServiceProvider for proper DI"
```

---

### Task 2: Refactor FireCommand to use DI

**Files:**
- Modify: `app/Console/Commands/LegalArtillery/FireCommand.php`

**Step 1: Update FireCommand to inject services**

Replace lines 64-88 where services are created directly. Change:

```php
// OLD: Direct instantiation
$llm = app(LlmClient::class);
$writer = new RecursiveDocumentWriter($llm);
// ...
$renderer = new DocxRenderer();
// ...
$dispatcher = new GmailDispatcher();
```

To:

```php
// NEW: Container resolution
$writer = app(RecursiveDocumentWriter::class);
// ...
$renderer = app(DocxRenderer::class);
// ...
$dispatcher = app(GmailDispatcher::class);
```

**Step 2: Run feature tests**

```bash
php artisan test tests/Feature/Commands/LegalArtillery/FireCommandTest.php
```

**Step 3: Commit**

```bash
git add app/Console/Commands/LegalArtillery/FireCommand.php
git commit -m "refactor: FireCommand uses DI container instead of direct instantiation"
```

---

### Task 3: Refactor BarrageCommand to use DI

**Files:**
- Modify: `app/Console/Commands/LegalArtillery/BarrageCommand.php`

**Step 1: Update BarrageCommand to inject services**

Replace lines 24-27 where services are created directly:

```php
// OLD
$llm = app(LlmClient::class);
$writer = new RecursiveDocumentWriter($llm);
$renderer = new DocxRenderer();

// NEW
$writer = app(RecursiveDocumentWriter::class);
$renderer = app(DocxRenderer::class);
```

And replace dispatcher creation:

```php
// OLD
$dispatcher = new GmailDispatcher();

// NEW
$dispatcher = app(GmailDispatcher::class);
```

**Step 2: Run feature tests**

```bash
php artisan test tests/Feature/Commands/LegalArtillery/BarrageCommandTest.php
```

**Step 3: Commit**

```bash
git add app/Console/Commands/LegalArtillery/BarrageCommand.php
git commit -m "refactor: BarrageCommand uses DI container instead of direct instantiation"
```

---

## Phase 2: Missing Test Coverage

### Task 4: Add SenderIdentityTest

**Files:**
- Create: `tests/Unit/DTOs/SenderIdentityTest.php`

**Step 1: Write the test**

```php
<?php

namespace Tests\Unit\DTOs;

use App\DTOs\SenderIdentity;
use Tests\TestCase;

class SenderIdentityTest extends TestCase
{
    public function test_creates_sender_identity_from_config(): void
    {
        $sender = SenderIdentity::fromConfig();

        $this->assertInstanceOf(SenderIdentity::class, $sender);
        $this->assertNotEmpty($sender->name);
        $this->assertIsString($sender->oib);
        $this->assertIsString($sender->address);
        $this->assertIsString($sender->email);
        $this->assertIsString($sender->phone);
    }

    public function test_constructs_with_all_properties(): void
    {
        $sender = new SenderIdentity(
            name: 'Test Name',
            oib: '12345678901',
            address: 'Test Address 123',
            email: 'test@example.com',
            phone: '+385 91 123 4567',
        );

        $this->assertEquals('Test Name', $sender->name);
        $this->assertEquals('12345678901', $sender->oib);
        $this->assertEquals('Test Address 123', $sender->address);
        $this->assertEquals('test@example.com', $sender->email);
        $this->assertEquals('+385 91 123 4567', $sender->phone);
    }

    public function test_properties_are_readonly(): void
    {
        $sender = SenderIdentity::fromConfig();

        $reflection = new \ReflectionClass($sender);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
```

**Step 2: Run test**

```bash
php artisan test tests/Unit/DTOs/SenderIdentityTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/DTOs/SenderIdentityTest.php
git commit -m "test: add SenderIdentityTest for complete DTO coverage"
```

---

### Task 5: Add LlmClientTest

**Files:**
- Create: `tests/Unit/Services/LegalArtillery/LlmClientTest.php`

**Step 1: Write the test**

```php
<?php

namespace Tests\Unit\Services\LegalArtillery;

use App\Services\LegalArtillery\LlmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmClientTest extends TestCase
{
    public function test_creates_from_config(): void
    {
        $client = LlmClient::fromConfig();

        $this->assertInstanceOf(LlmClient::class, $client);
    }

    public function test_generates_response_from_api(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Generated response'],
                ],
            ], 200),
        ]);

        $client = new LlmClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $response = $client->generate('System prompt', 'User prompt');

        $this->assertEquals('Generated response', $response);
    }

    public function test_throws_on_api_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $client = new LlmClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLM API call failed: 429');

        $client->generate('System', 'User');
    }

    public function test_combines_multiple_text_blocks(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'First block'],
                    ['type' => 'text', 'text' => 'Second block'],
                ],
            ], 200),
        ]);

        $client = new LlmClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $response = $client->generate('System', 'User');

        $this->assertEquals("First block\nSecond block", $response);
    }

    public function test_uses_custom_max_tokens(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'OK']],
            ], 200),
        ]);

        $client = new LlmClient(
            apiKey: 'test-key',
            model: 'claude-sonnet-4-20250514',
            maxTokens: 1024,
        );

        $client->generate('System', 'User', maxTokens: 512);

        Http::assertSent(function ($request) {
            return $request['max_tokens'] === 512;
        });
    }
}
```

**Step 2: Run test**

```bash
php artisan test tests/Unit/Services/LegalArtillery/LlmClientTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/Services/LegalArtillery/LlmClientTest.php
git commit -m "test: add LlmClientTest with API error handling coverage"
```

---

### Task 6: Add edge case tests for RecursiveDocumentWriter

**Files:**
- Modify: `tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php`

**Step 1: Add edge case tests**

Add these test methods to the existing test file:

```php
public function test_handles_malformed_json_outline(): void
{
    $profile = DocumentProfile::fromConfig('predsjednik_suda');
    $context = CaseContext::fromConfig();
    $llm = Mockery::mock(LlmClient::class);

    $llm->shouldReceive('generate')
        ->once()
        ->andReturn('This is not valid JSON');

    $writer = new RecursiveDocumentWriter($llm);
    $outline = $writer->generateOutline($profile, $context);

    // Should return empty sections array, not crash
    $this->assertArrayHasKey('sections', $outline);
    $this->assertEmpty($outline['sections']);
}

public function test_handles_json_in_markdown_code_block(): void
{
    $profile = DocumentProfile::fromConfig('predsjednik_suda');
    $context = CaseContext::fromConfig();
    $llm = Mockery::mock(LlmClient::class);

    $llm->shouldReceive('generate')
        ->once()
        ->andReturn("```json\n{\"sections\": [{\"key\": \"test\", \"title\": \"Test\", \"guidance\": \"Test guidance\"}]}\n```");

    $writer = new RecursiveDocumentWriter($llm);
    $outline = $writer->generateOutline($profile, $context);

    $this->assertCount(1, $outline['sections']);
    $this->assertEquals('test', $outline['sections'][0]['key']);
}

public function test_handles_empty_additional_context(): void
{
    $profile = DocumentProfile::fromConfig('predsjednik_suda');
    $context = CaseContext::fromConfig();
    $llm = Mockery::mock(LlmClient::class);

    $llm->shouldReceive('generate')
        ->andReturn(json_encode(['sections' => []]));

    $writer = new RecursiveDocumentWriter($llm);
    $outline = $writer->generateOutline($profile, $context, []);

    $this->assertArrayHasKey('sections', $outline);
}
```

**Step 2: Run tests**

```bash
php artisan test tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php
git commit -m "test: add edge case tests for malformed LLM responses"
```

---

## Phase 3: Security Fixes

### Task 7: Sanitize email headers against CRLF injection

**Files:**
- Modify: `app/Services/LegalArtillery/GmailDispatcher.php`

**Step 1: Add header sanitization**

Add a private method and use it in `buildMimeMessage()`:

```php
private function sanitizeHeaderValue(string $value): string
{
    return str_replace(["\r", "\n", "\0"], '', $value);
}
```

Update `buildMimeMessage()` to sanitize all header values:

```php
public function buildMimeMessage(string $to, string $subject, string $body, ?string $attachmentPath = null): string
{
    $boundary = md5(uniqid((string) rand(), true));
    $fromEmail = $this->sanitizeHeaderValue(config('legal-artillery.gmail.from_email', ''));
    $fromName = $this->sanitizeHeaderValue(config('legal-artillery.gmail.from_name', ''));
    $cc = config('legal-artillery.gmail.cc');

    $headers = [];
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    if ($to) {
        $headers[] = "To: " . $this->sanitizeHeaderValue($to);
    }
    if ($cc) {
        $headers[] = "Cc: " . $this->sanitizeHeaderValue($cc);
    }
    // ... rest remains the same
```

**Step 2: Add test for sanitization**

Add to `GmailDispatcherTest.php`:

```php
public function test_sanitizes_header_values(): void
{
    $dispatcher = new GmailDispatcher();

    $mime = $dispatcher->buildMimeMessage(
        "test@example.com\r\nBcc: attacker@evil.com",
        "Subject\r\nX-Injected: header",
        'Body',
        null
    );

    $this->assertStringNotContainsString('Bcc:', $mime);
    $this->assertStringNotContainsString('X-Injected:', $mime);
}
```

**Step 3: Run tests**

```bash
php artisan test tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php
```
Expected: PASS

**Step 4: Commit**

```bash
git add app/Services/LegalArtillery/GmailDispatcher.php tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php
git commit -m "security: sanitize email headers against CRLF injection"
```

---

## Phase 4: Error Handling Improvements

### Task 8: Add logging for malformed JSON in RecursiveDocumentWriter

**Files:**
- Modify: `app/Services/LegalArtillery/RecursiveDocumentWriter.php`

**Step 1: Add warning log for malformed JSON**

Update the `generateOutline` method around line 112:

```php
// Parse JSON from response (handle markdown code blocks)
$json = $this->extractJson($response);
$decoded = json_decode($json, true);

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    Log::warning('LegalArtillery: Failed to parse outline JSON', [
        'error' => json_last_error_msg(),
        'response_preview' => substr($response, 0, 200),
    ]);
    return ['sections' => []];
}

return $decoded ?? ['sections' => []];
```

**Step 2: Run tests**

```bash
php artisan test tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add app/Services/LegalArtillery/RecursiveDocumentWriter.php
git commit -m "fix: log warning for malformed LLM JSON responses"
```

---

### Task 9: Fix double Carbon::now() call in CaseContext

**Files:**
- Modify: `app/DTOs/CaseContext.php`

**Step 1: Fix the timing issue**

Update `toTemplateVars()` method:

```php
public function toTemplateVars(): array
{
    $now = Carbon::now();

    return [
        'case_number' => $this->caseNumber,
        'search_date' => $this->searchDate,
        'archive_date' => $this->archiveDate,
        'address_searched' => $this->addressSearched,
        'warrant_reference' => $this->warrantReference,
        'police_klasa' => $this->policeRequestKlasa,
        'police_urbroj' => $this->policeRequestUrbroj,
        'legal_basis_warrant' => $this->legalBasisWarrant,
        'suspected_offense' => $this->suspectedOffense,
        'judge' => $this->judge,
        'denial_date' => $this->denialDate,
        'county_response_date' => $this->countyCourtResponseDate,
        'sender_name' => $this->sender->name,
        'sender_oib' => $this->sender->oib,
        'sender_address' => $this->sender->address,
        'sender_email' => $this->sender->email,
        'sender_phone' => $this->sender->phone,
        'today_date' => $now->format('d. F Y.'),
        'today_date_iso' => $now->toDateString(),
    ];
}
```

**Step 2: Run tests**

```bash
php artisan test tests/Unit/DTOs/CaseContextTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add app/DTOs/CaseContext.php
git commit -m "fix: use single Carbon::now() call in toTemplateVars()"
```

---

## Phase 5: Documentation

### Task 10: Add PHPDoc to RecursiveDocumentWriter

**Files:**
- Modify: `app/Services/LegalArtillery/RecursiveDocumentWriter.php`

**Step 1: Add PHPDoc blocks**

Add to the top of the class:

```php
/**
 * Recursively generates legal documents using LLM.
 *
 * Pipeline: (1) Generate outline → (2) Generate each section → (3) Polish final document
 */
class RecursiveDocumentWriter
{
```

Add to `generate()` method:

```php
/**
 * Full recursive generation pipeline.
 *
 * @param DocumentProfile $profile The document type configuration
 * @param CaseContext $context Case-specific data for interpolation
 * @param array<string, string> $additionalContext Extra context key-value pairs
 * @return array{
 *     profile_key: string,
 *     profile_name: string,
 *     outline: array{sections: array<array{key: string, title: string, guidance: string}>},
 *     sections: array<array{key: string, title: string, content: string}>,
 *     content: string,
 *     generated_at: string
 * }
 */
public function generate(DocumentProfile $profile, CaseContext $context, array $additionalContext = []): array
```

Add to `generateOutline()`:

```php
/**
 * Generate document outline from profile structure.
 *
 * @param DocumentProfile $profile The document type configuration
 * @param CaseContext $context Case-specific data
 * @param array<string, string> $additional Extra context
 * @return array{sections: array<array{key: string, title: string, guidance: string}>}
 */
public function generateOutline(DocumentProfile $profile, CaseContext $context, array $additional = []): array
```

Add to `generateSection()`:

```php
/**
 * Generate content for a single section.
 *
 * @param DocumentProfile $profile Document configuration
 * @param CaseContext $context Case data
 * @param array{key: string, title: string, guidance: string} $section Section to generate
 * @param array<array{title: string, content: string}> $previousSections Already generated sections for context
 * @param array<string, string> $additional Extra context
 * @return string Generated section content
 */
public function generateSection(
```

**Step 2: Run tests to ensure no syntax errors**

```bash
php artisan test tests/Unit/Services/LegalArtillery/RecursiveDocumentWriterTest.php
```
Expected: PASS

**Step 3: Commit**

```bash
git add app/Services/LegalArtillery/RecursiveDocumentWriter.php
git commit -m "docs: add PHPDoc to RecursiveDocumentWriter with return type shapes"
```

---

### Task 11: Add class-level docblocks to DTOs

**Files:**
- Modify: `app/DTOs/DocumentProfile.php`
- Modify: `app/DTOs/CaseContext.php`
- Modify: `app/DTOs/SenderIdentity.php`

**Step 1: Add docblocks**

DocumentProfile.php:
```php
<?php

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Immutable data transfer object representing a legal document profile.
 *
 * Each profile defines: recipient, legal basis, tone, structure, and formatting
 * for a specific type of legal correspondence (e.g., court motion, ombudsman complaint).
 *
 * Profiles are loaded from config/legal-artillery.php.
 */
class DocumentProfile
```

CaseContext.php:
```php
<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Immutable context object containing case-specific data.
 *
 * Provides template variables for document generation and string interpolation.
 * Loaded from config/legal-artillery.php case_context section.
 */
class CaseContext
```

SenderIdentity.php:
```php
<?php

namespace App\DTOs;

/**
 * Immutable identity of the document sender/petitioner.
 *
 * Contains personal identification data (name, OIB, address, contact).
 * Loaded from config/legal-artillery.php sender section.
 */
class SenderIdentity
```

**Step 2: Run tests**

```bash
php artisan test tests/Unit/DTOs/
```
Expected: PASS

**Step 3: Commit**

```bash
git add app/DTOs/DocumentProfile.php app/DTOs/CaseContext.php app/DTOs/SenderIdentity.php
git commit -m "docs: add class-level docblocks to all DTOs"
```

---

### Task 12: Use Laravel File facade instead of mkdir

**Files:**
- Modify: `app/Services/LegalArtillery/DocxRenderer.php`
- Modify: `app/Console/Commands/LegalArtillery/GmailAuthCommand.php`

**Step 1: Update DocxRenderer**

Replace:
```php
if (!is_dir($this->outputDir)) {
    mkdir($this->outputDir, 0755, true);
}
```

With:
```php
use Illuminate\Support\Facades\File;

// In constructor:
File::ensureDirectoryExists($this->outputDir);
```

**Step 2: Update GmailAuthCommand**

Replace:
```php
$tokenDir = dirname($tokenPath);
if (!is_dir($tokenDir)) {
    mkdir($tokenDir, 0700, true);
}
```

With:
```php
use Illuminate\Support\Facades\File;

// Before saving token:
File::ensureDirectoryExists(dirname($tokenPath), 0700);
```

**Step 3: Run tests**

```bash
php artisan test tests/Unit/Services/LegalArtillery/DocxRendererTest.php
```
Expected: PASS

**Step 4: Commit**

```bash
git add app/Services/LegalArtillery/DocxRenderer.php app/Console/Commands/LegalArtillery/GmailAuthCommand.php
git commit -m "refactor: use Laravel File facade instead of mkdir()"
```

---

## Final Verification

### Task 13: Run full test suite and push

**Step 1: Run all related tests**

```bash
php artisan test tests/Unit/DTOs/ tests/Unit/Services/LegalArtillery/ tests/Unit/Agents/ tests/Feature/Commands/LegalArtillery/
```
Expected: All PASS

**Step 2: Push changes**

```bash
git push origin claude/execute-sprint-plan-e3J9v
```

---

## Summary

| Phase | Tasks | Issues Addressed |
|-------|-------|-----------------|
| 1. DI & Service Provider | 1-3 | Major: Missing service provider, direct instantiation |
| 2. Test Coverage | 4-6 | Major: Missing SenderIdentityTest, LlmClientTest |
| 3. Security | 7 | Minor: CRLF injection in email headers |
| 4. Error Handling | 8-9 | Minor: Silent JSON failures, double Carbon call |
| 5. Documentation | 10-12 | Major/Minor: Missing PHPDoc, docblocks, Laravel conventions |
| Final | 13 | Verification and push |

**Expected Grade After Remediation: 9.0/10**
