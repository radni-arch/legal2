# Test Data Scenario Builders

This directory contains **Scenario Builders** for creating realistic test data for the Croatian Legal AI Defense System.

## Overview

Scenario builders provide a fluent, expressive API for creating complex test scenarios without repeating factory boilerplate. They wrap existing Laravel factories and add domain-specific logic.

## Available Builders

### 1. CriminalCaseScenarioBuilder

Build complete criminal case workflows with documents, evidence, and agents.

```php
use Tests\TestData\Builders\CriminalCaseScenarioBuilder;

// Simple criminal case with drug charges
$case = CriminalCaseScenarioBuilder::make()
    ->withDrugCharges()
    ->build();

// Complex case with multiple components
$case = CriminalCaseScenarioBuilder::make()
    ->withDrugCharges()
    ->withHomeSearch()
    ->withWitnesses(3)
    ->withFabricatedEvidence()
    ->build();
```

**Methods:**
- `withDrugCharges()` - Add drug-related charges and documentation
- `withHomeSearch()` - Add home search warrant and execution documents
- `withFabricatedEvidence()` - Add fabricated evidence for misconduct testing
- `withWitnesses(int $count)` - Add witness statements
- `build()` - Create and persist the LegalCase with all documents

### 2. EvidenceChainBuilder

Build coherent evidence sequences with temporal consistency.

```php
use Tests\TestData\Builders\EvidenceChainBuilder;

// Communication chain with timeline
$evidence = EvidenceChainBuilder::make()
    ->withCommunicationChain(5)  // 5 emails/messages/calls
    ->withPhysicalEvidence()
    ->withTimeline()  // Enforce chronological ordering
    ->build();

// Returns Collection of Evidence models
$evidence->each(function($item) {
    echo $item->title . " - " . $item->created_at;
});
```

**Methods:**
- `withCommunicationChain(int $count)` - Add communication evidence (emails, SMS, calls)
- `withPhysicalEvidence()` - Add physical evidence items
- `withTimeline()` - Enforce chronological ordering
- `build()` - Create and persist the evidence collection

### 3. MisconductScenarioBuilder

Build prosecutorial misconduct patterns detectable by the AI system.

```php
use Tests\TestData\Builders\MisconductScenarioBuilder;

// Case with backdated documents
$case = MisconductScenarioBuilder::make()
    ->withBackdatedDocuments()
    ->build();

// Complex misconduct case
$case = MisconductScenarioBuilder::make()
    ->withBackdatedDocuments()
    ->withHiddenEvidence()
    ->withFabricatedPC()
    ->build();
```

**Methods:**
- `withBackdatedDocuments()` - Add documents dated before case filing
- `withHiddenEvidence()` - Add suppressed exculpatory evidence
- `withFabricatedPC()` - Add fabricated probable cause documentation
- `build()` - Create and persist the LegalCase with misconduct patterns

## Usage in Tests

```php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\UsesTestDatabase;
use Tests\TestData\Builders\CriminalCaseScenarioBuilder;

class MisconductDetectionTest extends TestCase
{
    use UsesTestDatabase;

    public function test_system_detects_backdated_documents(): void
    {
        // Arrange
        $case = MisconductScenarioBuilder::make()
            ->withBackdatedDocuments()
            ->build();

        // Act
        $analysis = $this->app->make(MisconductDetector::class)
            ->analyze($case);

        // Assert
        $this->assertTrue($analysis->hasBackdatedDocuments());
        $this->assertCount(2, $analysis->getBackdatedDocuments());
    }
}
```

## Design Principles

1. **Fluent API** - Chainable methods for readable test setup
2. **Use Existing Factories** - Wrap Laravel factories, don't duplicate
3. **Croatian Legal Context** - Use Croatian terminology (ZKP, etc.)
4. **Temporal Consistency** - Evidence chains have realistic timelines
5. **Detectable Patterns** - Misconduct scenarios include metadata markers

## TDD Compliance

All builders were developed using strict Test-Driven Development:
1. Write test first (RED)
2. Verify test fails
3. Write minimal implementation (GREEN)
4. Verify test passes
5. Refactor (REFACTOR)

## Testing

Run builder tests:

```bash
# All builder tests
composer test tests/Unit/TestData/Builders/

# Specific builder
composer test tests/Unit/TestData/Builders/CriminalCaseScenarioBuilderTest.php
composer test tests/Unit/TestData/Builders/EvidenceChainBuilderTest.php
composer test tests/Unit/TestData/Builders/MisconductScenarioBuilderTest.php
```

## Architecture

```
tests/
├── TestData/
│   └── Builders/              # Builder implementations
│       ├── CriminalCaseScenarioBuilder.php
│       ├── EvidenceChainBuilder.php
│       ├── MisconductScenarioBuilder.php
│       └── README.md
└── Unit/
    └── TestData/
        └── Builders/          # Builder tests
            ├── CriminalCaseScenarioBuilderTest.php
            ├── EvidenceChainBuilderTest.php
            └── MisconductScenarioBuilderTest.php
```

## Future Extensions

Potential future builders:
- `CivilCaseScenarioBuilder` - Civil litigation scenarios
- `AppellateScenarioBuilder` - Appeal workflows
- `DiscoveryScenarioBuilder` - Document discovery scenarios
- `TimelineScenarioBuilder` - Complex temporal event chains
