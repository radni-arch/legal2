# MetadataBuilder Test Suite

## Overview
This test suite provides comprehensive coverage for the `MetadataBuilder` service, which generates Schema.org-compliant metadata for Croatian legal articles using the European Legislation Identifier (ELI) format.

## Test Results
✅ **52 tests passing**
✅ **100 assertions**
✅ **No external dependencies** (pure unit tests)

## Test Coverage

### Basic Metadata Structure (4 tests)
- Complete metadata structure with all required fields
- Schema.org context (`https://schema.org`)
- Creative Work type
- Croatian language setting (`hr`)

### ID Generation (6 tests)
- URN format: `urn:hr-law:{path}#clanak-{number}`
- ELI resource parsing and path extraction
- Article number integration
- Leading slash trimming from paths
- ID and identifier field synchronization
- Complex ELI resource path handling

### Name/Title Formatting (3 tests)
- Article name format: `{title} – Članak {number}`
- Title preservation
- Special Croatian characters (međunarodnom, đ, č, ć, š, ž)

### Date Published (2 tests)
- Date from context
- Multiple date format support (ISO 8601, etc.)

### Legislation Section (isBasedOn) (6 tests)
- Legislation type setting
- ELI resource as identifier
- ELI expression in sameAs array
- HTML URL in sameAs array
- PDF URL in sameAs array
- Complete sameAs array with all three URLs

### About Section (8 tests)
- Document type (`type_document`)
- Fixed NN part (`SL` - Službeni list)
- Year from context (`nn_year`)
- Edition from context (`nn_edition`)
- Act number from context (`nn_act`)
- Consolidated text flag (default `false`)
- Consolidated text when provided (`true`/`false`)

### Article Section (6 tests)
- Article number conversion to string
- String article numbers (e.g., "15a")
- Heading chain array
- Empty heading chain default
- Text checksum when provided
- Text checksum null default

### File Section (6 tests)
- File path when provided
- File path null default
- File size in bytes
- File bytes null default
- SHA-256 hash
- SHA-256 null default

### Generator Section (5 tests)
- Generator name (`hr-law-ingestor`)
- Custom version number
- Default version (`1.0.0`)
- Custom generated_at timestamp
- Auto-generated UTC ISO 8601 timestamp

### Integration Tests (6 tests)
- Complete metadata with all optional fields
- Minimal metadata with only required fields
- Numeric value type handling
- Empty heading chain handling
- Long heading chains (4+ levels)
- Special characters in URLs preservation

## Schema.org Structure

The MetadataBuilder generates metadata following this structure:

```json
{
  "@context": "https://schema.org",
  "@type": "CreativeWork",
  "id": "urn:hr-law:hr/NN/2024/50#clanak-25",
  "identifier": "urn:hr-law:hr/NN/2024/50#clanak-25",
  "inLanguage": "hr",
  "name": "Zakon o radu – Članak 25",
  "datePublished": "2024-05-15",
  "isBasedOn": {
    "@type": "Legislation",
    "identifier": "http://lex.hr/hr/NN/2024/50",
    "sameAs": [
      "http://lex.hr/hr/NN/2024/50/hrv",
      "https://nn.hr/2024/50.html",
      "https://nn.hr/2024/50.pdf"
    ]
  },
  "about": {
    "type_document": "ZAKON",
    "nn_part": "SL",
    "nn_year": 2024,
    "nn_edition": 50,
    "nn_act": 1234,
    "is_consolidated_text": true
  },
  "article": {
    "number": "25",
    "heading_chain": ["Dio I", "Glava II", "Odjeljak 3"],
    "text_checksum": "abc123"
  },
  "file": {
    "path": "/storage/laws/zakon-o-radu.json",
    "bytes": 102400,
    "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  },
  "generator": {
    "name": "hr-law-ingestor",
    "version": "2.1.0",
    "generated_at": "2024-05-15T12:00:00Z"
  }
}
```

## Running the Tests

```bash
# Run all MetadataBuilder tests
vendor/bin/phpunit tests/Unit/Services/MetadataBuilderTest.php

# Or use artisan
php artisan test --filter=MetadataBuilderTest

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Unit/Services/MetadataBuilderTest.php
```

## Required Context Fields

### Mandatory Fields
- `eli_resource` - ELI resource identifier (URL)
- `eli_expression` - ELI expression identifier (URL)
- `html_url` - HTML version URL
- `pdf_url` - PDF version URL
- `article_number` - Article number (string or int)
- `title` - Law title
- `date_publication` - Publication date
- `type_document` - Document type (e.g., "ZAKON")
- `year` - Publication year
- `edition` - Narodne Novine edition number
- `act` - Act number within edition

### Optional Fields
- `is_consolidated` - Boolean, defaults to `false`
- `heading_chain` - Array of heading hierarchy, defaults to `[]`
- `text_checksum` - Text checksum, defaults to `null`
- `file_path` - File path, defaults to `null`
- `file_bytes` - File size in bytes, defaults to `null`
- `file_sha256` - SHA-256 hash, defaults to `null`
- `generator_version` - Version string, defaults to `"1.0.0"`
- `generated_at` - ISO 8601 timestamp, auto-generated if not provided

## ELI (European Legislation Identifier) Format

The builder follows the ELI standard for legal identifiers:

### Resource Format
```
http://lex.hr/{jurisdiction}/{publication}/{year}/{edition}
```

Example: `http://lex.hr/hr/NN/2024/50`

### Expression Format
```
http://lex.hr/{jurisdiction}/{publication}/{year}/{edition}/{language}
```

Example: `http://lex.hr/hr/NN/2024/50/hrv`

### URN Format (Generated ID)
```
urn:hr-law:{path}#clanak-{article_number}
```

Example: `urn:hr-law:hr/NN/2024/50#clanak-25`

## Croatian Legal Document Structure

### NN (Narodne Novine)
Croatian Official Gazette publication system:
- **SL** (Službeni list) - Official part
- **Year** - Publication year (e.g., 2024)
- **Edition** - Issue number (e.g., 50)
- **Act** - Act number within edition

### Document Types
- **ZAKON** - Law
- **PRAVILNIK** - Regulation
- **UREDBA** - Decree
- **ODLUKA** - Decision

### Heading Chain
Hierarchical structure of the law:
1. **Dio** - Part (e.g., "Dio I - Opće odredbe")
2. **Glava** - Chapter (e.g., "Glava I - Temeljne odredbe")
3. **Odjeljak** - Section (e.g., "Odjeljak 1 - Načela")
4. **Pododjeljak** - Subsection

### Article Numbering
- Standard: `"1"`, `"2"`, `"3"`
- Modified: `"15a"`, `"15b"` (amendments)
- Always converted to string in metadata

## Test Patterns

### Basic Test Structure
```php
public function it_tests_specific_feature()
{
    $context = $this->createBasicContext([
        'field' => 'value',
    ]);

    $metadata = $this->builder->buildArticleMetadata($context);

    $this->assertEquals('expected', $metadata['section']['field']);
}
```

### Testing Defaults
```php
public function it_sets_field_to_default_when_not_provided()
{
    $context = $this->createBasicContext();
    unset($context['optional_field']);

    $metadata = $this->builder->buildArticleMetadata($context);

    $this->assertNull($metadata['section']['optional_field']);
}
```

### Testing Type Conversions
```php
public function it_converts_article_number_to_string()
{
    $context = $this->createBasicContext([
        'article_number' => 42,  // Integer
    ]);

    $metadata = $this->builder->buildArticleMetadata($context);

    $this->assertIsString($metadata['article']['number']);
    $this->assertEquals('42', $metadata['article']['number']);
}
```

## No External Dependencies

This service is a **pure function** with:
- ✅ No database queries
- ✅ No API calls
- ✅ No file system operations
- ✅ No caching
- ✅ No service dependencies

This makes testing:
- **Fast** - No I/O operations
- **Reliable** - No external failures
- **Simple** - No mocking required
- **Deterministic** - Same input = same output

## Future Enhancements

Potential areas for expansion:
1. Support for amendments (article modifications)
2. Multi-language metadata (bilingual laws)
3. Referenced legislation tracking
4. Version history metadata
5. Consolidated text diff metadata
6. Additional Schema.org types (LegalDocument, etc.)
7. Validation of ELI format
8. Custom schema extensions

## Related Standards

- **Schema.org**: https://schema.org/
- **ELI (European Legislation Identifier)**: https://eur-lex.europa.eu/eli-register/about.html
- **ISO 8601**: Date and time format
- **URN (Uniform Resource Name)**: RFC 8141
- **SHA-256**: FIPS 180-4 cryptographic hash

## Notes

### PHPUnit Deprecations
The test suite uses `@test` annotations which will be deprecated in PHPUnit 12. These can be migrated to PHP 8 attributes:

```php
// Current (PHPUnit 10/11)
/** @test */
public function it_does_something() { }

// Future (PHPUnit 12+)
#[Test]
public function it_does_something() { }
```

### Croatian Character Encoding
Tests verify UTF-8 handling of Croatian diacritics:
- č, ć, dž, đ, lj, nj, š, ž

### Timestamp Precision
The auto-generated timestamp test uses `gmdate('c')` which includes timezone offset. Tests allow for slight timing variations (before/after snapshots).
