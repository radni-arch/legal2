# Testing Guide - Legal Reasoning System

**Date:** 2025-10-28
**Version:** 1.0
**Coverage:** All 12 Services + API Endpoints + Integration Flows

---

## Documentation Navigation

For comprehensive testing documentation:

- **📖 [Main Testing Guide](TESTING.md)** - Comprehensive testing approach and database strategy
- **📊 [Coverage Analysis](TEST_COVERAGE_ANALYSIS_AND_PLAN.md)** - Coverage improvement plan
- **📁 [Neo4j Test Coverage Audit](neo4j-test-coverage-audit.md)** - Graph database test coverage

---

## Table of Contents

1. [Overview](#overview)
2. [Test Structure](#test-structure)
3. [Running Tests](#running-tests)
4. [Unit Tests](#unit-tests)
5. [API Tests](#api-tests)
6. [Integration Tests](#integration-tests)
7. [Test Coverage](#test-coverage)
8. [Writing New Tests](#writing-new-tests)
9. [Continuous Integration](#continuous-integration)

---

## Overview

The Legal Reasoning System has comprehensive test coverage across three levels:

- **Unit Tests**: Test individual service methods in isolation with mocked dependencies
- **API Tests**: Test HTTP endpoints and request/response handling
- **Integration Tests**: Test complete end-to-end workflows with minimal mocking

### Test Framework

- **Framework**: PHPUnit 10.x (Laravel 12.0 default)
- **Database**: SQLite in-memory for fast testing
- **Mocking**: Mockery for dependency mocking
- **Factories**: Laravel Model Factories for test data

---

## Test Structure

```
tests/
├── Unit/
│   └── Services/
│       └── LegalReasoning/
│           ├── OutcomePredictorTest.php
│           ├── FeatureExtractorTest.php
│           ├── PredictiveAnalyticsTest.php
│           ├── ConflictResolverTest.php
│           ├── CitationAnalyzerTest.php
│           ├── LogicEngineTest.php
│           ├── DurationEstimatorTest.php
│           ├── ImpactAnalyzerTest.php
│           ├── ArgumentGeneratorTest.php
│           ├── RiskAssessorTest.php
│           ├── StrategicPlannerTest.php
│           └── StrategyBuilderTest.php
│
├── Feature/
│   └── Api/
│       ├── ReasoningControllerTest.php
│       ├── AnalyticsControllerTest.php
│       └── StrategyControllerTest.php
│
└── Integration/
    ├── StrategyGenerationFlowTest.php
    ├── OutcomePredictionFlowTest.php
    └── RiskAssessmentFlowTest.php
```

---

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suite

```bash
# Unit tests only
php artisan test --testsuite=Unit

# Feature/API tests only
php artisan test --testsuite=Feature

# Integration tests (requires OpenAI API)
php artisan test tests/Integration
```

### Run Specific Test File

```bash
php artisan test tests/Unit/Services/LegalReasoning/OutcomePredictorTest.php
```

### Run Specific Test Method

```bash
php artisan test --filter=it_predicts_outcome_successfully
```

### With Coverage Report

```bash
php artisan test --coverage
php artisan test --coverage-html coverage-report
```

### Parallel Testing (Faster)

```bash
php artisan test --parallel
```

---

## Unit Tests

Unit tests verify individual service methods in isolation with mocked dependencies.

### Example: OutcomePredictorTest

**Location:** `tests/Unit/Services/LegalReasoning/OutcomePredictorTest.php`

**Tests:**
- ✅ `it_predicts_outcome_successfully` - Main prediction flow
- ✅ `it_handles_case_not_found` - Error handling
- ✅ `it_calculates_probability_distribution_correctly` - Algorithm correctness
- ✅ `it_identifies_key_factors_from_features` - Factor extraction

**Mocked Dependencies:**
- `OpenAIService` - LLM completion calls
- `CaseVectorStoreService` - Vector search
- `FeatureExtractor` - Feature extraction

### Example: FeatureExtractorTest

**Location:** `tests/Unit/Services/LegalReasoning/FeatureExtractorTest.php`

**Tests:**
- ✅ `it_extracts_case_features_successfully` - Feature extraction
- ✅ `it_calculates_complexity_score_correctly` - Complexity calculation
- ✅ `it_persists_features_to_database` - Database persistence
- ✅ `it_uses_cached_features_when_recent` - Caching (< 7 days)
- ✅ `it_refreshes_features_when_requested` - Cache refresh
- ✅ `it_handles_empty_legal_issues_gracefully` - Edge cases

### Example: StrategyBuilderTest

**Location:** `tests/Unit/Services/LegalReasoning/StrategyBuilderTest.php`

**Tests:**
- ✅ `it_builds_comprehensive_strategy_successfully` - Full orchestration
- ✅ `it_calculates_confidence_score_correctly` - Confidence scoring
- ✅ `it_performs_swot_analysis` - SWOT analysis
- ✅ `it_generates_strategic_recommendations` - Recommendations
- ✅ `it_handles_high_risk_cases_with_settlement_recommendation` - Risk-based logic

### All Unit Tests (12 Services)

| Service | Test File | Key Tests |
|---------|-----------|-----------|
| OutcomePredictor | OutcomePredictorTest.php | Prediction, probability, factors |
| FeatureExtractor | FeatureExtractorTest.php | Extraction, caching, persistence |
| PredictiveAnalytics | PredictiveAnalyticsTest.php | Orchestration, accuracy tracking |
| ConflictResolver | ConflictResolverTest.php | Conflict detection, resolution |
| CitationAnalyzer | CitationAnalyzerTest.php | Authority scoring, metrics |
| LogicEngine | LogicEngineTest.php | Logic parsing, deductive reasoning |
| DurationEstimator | DurationEstimatorTest.php | Duration prediction, milestones |
| ImpactAnalyzer | ImpactAnalyzerTest.php | Impact scoring, trends |
| ArgumentGenerator | ArgumentGeneratorTest.php | IRAC, counter-arguments |
| RiskAssessor | RiskAssessorTest.php | Risk assessment, mitigation |
| StrategicPlanner | StrategicPlannerTest.php | Phase planning, resources |
| StrategyBuilder | StrategyBuilderTest.php | Full orchestration, SWOT |

---

## API Tests

API tests verify HTTP endpoints, request validation, and response structure.

### Example: AnalyticsControllerTest

**Location:** `tests/Feature/Api/AnalyticsControllerTest.php`

**Tests:**
- ✅ `it_predicts_outcome_successfully` - POST /analytics/predict-outcome/{id}
- ✅ `it_returns_404_for_non_existent_case` - Error handling
- ✅ `it_estimates_duration_successfully` - POST /analytics/estimate-duration/{id}
- ✅ `it_provides_comprehensive_analytics` - POST /analytics/comprehensive/{id}
- ✅ `it_handles_batch_predictions` - POST /analytics/batch-predict
- ✅ `it_validates_required_fields_for_batch_predict` - Validation
- ✅ `it_throttles_requests` - Rate limiting (60/min)
- ✅ `it_logs_prediction_requests` - Logging

### Example: StrategyControllerTest

**Location:** `tests/Feature/Api/StrategyControllerTest.php`

**Tests:**
- ✅ `it_builds_comprehensive_strategy` - POST /strategy/comprehensive/{id}
- ✅ `it_generates_arguments` - POST /strategy/generate-arguments/{id}
- ✅ `it_assesses_risks` - POST /strategy/assess-risks/{id}
- ✅ `it_creates_action_plan` - POST /strategy/action-plan/{id}
- ✅ `it_validates_objectives_format` - Validation
- ✅ `it_handles_errors_gracefully` - Error handling
- ✅ `it_requires_authentication_for_strategy_endpoints` - Auth (if enabled)

### All API Endpoints Tested

| Endpoint | Controller | Test Coverage |
|----------|------------|---------------|
| POST /reasoning/analyze-conflict | ReasoningController | ✅ |
| POST /reasoning/resolve-conflict | ReasoningController | ✅ |
| POST /reasoning/authority-score | ReasoningController | ✅ |
| POST /reasoning/parse-logic | ReasoningController | ✅ |
| POST /reasoning/apply-deductive | ReasoningController | ✅ |
| POST /analytics/predict-outcome/{id} | AnalyticsController | ✅ |
| POST /analytics/estimate-duration/{id} | AnalyticsController | ✅ |
| POST /analytics/comprehensive/{id} | AnalyticsController | ✅ |
| POST /analytics/analyze-impact/{id} | AnalyticsController | ✅ |
| POST /analytics/batch-predict | AnalyticsController | ✅ |
| POST /strategy/build/{id} | StrategyController | ✅ |
| POST /strategy/comprehensive/{id} | StrategyController | ✅ |
| POST /strategy/generate-arguments/{id} | StrategyController | ✅ |
| POST /strategy/assess-risks/{id} | StrategyController | ✅ |
| POST /strategy/action-plan/{id} | StrategyController | ✅ |

---

## Integration Tests

Integration tests verify end-to-end workflows with minimal mocking.

**Note:** Integration tests require live OpenAI API access and are marked as skipped by default.

### Example: StrategyGenerationFlowTest

**Location:** `tests/Integration/StrategyGenerationFlowTest.php`

**Tests:**
- ✅ `it_completes_full_strategy_generation_workflow` - Complete E2E flow
- ✅ `it_extracts_and_persists_features_correctly` - Feature persistence
- ✅ `it_predicts_outcome_and_tracks_prediction` - Outcome prediction E2E
- ✅ `it_generates_arguments_with_precedent_support` - Argument generation E2E
- ✅ `it_assesses_risks_comprehensively` - Risk assessment E2E
- ✅ `it_creates_detailed_action_plan` - Action planning E2E
- ✅ `it_handles_concurrent_strategy_requests` - Concurrency

### Running Integration Tests

```bash
# Enable integration tests by removing @markTestSkipped
# Set OpenAI API key
export OPENAI_API_KEY=your_key_here

# Run integration tests
php artisan test tests/Integration --stop-on-failure
```

### Complete E2E Flows Tested

1. **Feature Extraction Flow**
   - Case → Feature Extraction → Embedding → Database → Cache

2. **Outcome Prediction Flow**
   - Case → Features → Vector Search → Similar Cases → LLM Analysis → Prediction → Persistence

3. **Argument Generation Flow**
   - Case → Issues → Precedents → Laws → IRAC → LLM Enhancement → Counter-arguments → Rebuttals

4. **Risk Assessment Flow**
   - Case → Legal Risks → Evidentiary Risks → Strategic Risks → Scoring → Mitigation

5. **Strategy Generation Flow**
   - Case → Analysis → Arguments → Risks → Plan → SWOT → Synthesis → Persistence

---

## Test Coverage

### Current Coverage (Target: 80%+)

| Component | Coverage | Status |
|-----------|----------|--------|
| Services | 85% | ✅ Excellent |
| Controllers | 90% | ✅ Excellent |
| Models | 75% | ✅ Good |
| Overall | 83% | ✅ Excellent |

### Running Coverage Reports

```bash
# Generate HTML coverage report
php artisan test --coverage-html coverage-report

# Open in browser
open coverage-report/index.html

# Or use coverage text output
php artisan test --coverage --min=80
```

### Critical Paths (100% Coverage Required)

- ✅ OutcomePredictor::predictOutcome()
- ✅ StrategyBuilder::buildCaseStrategy()
- ✅ FeatureExtractor::extractAndPersistCaseFeatures()
- ✅ RiskAssessor::assessRisks()
- ✅ ArgumentGenerator::generateArguments()

---

## Writing New Tests

### Unit Test Template

```php
<?php

namespace Tests\Unit\Services\LegalReasoning;

use Tests\TestCase;
use App\Services\LegalReasoning\YourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class YourServiceTest extends TestCase
{
    use RefreshDatabase;

    protected YourService $service;
    protected $dependencyMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencyMock = Mockery::mock(DependencyClass::class);
        $this->service = new YourService($this->dependencyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_does_something_correctly()
    {
        // Arrange
        $this->dependencyMock
            ->shouldReceive('method')
            ->once()
            ->andReturn('value');

        // Act
        $result = $this->service->doSomething();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### API Test Template

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_handles_request_successfully()
    {
        // Arrange
        $case = LegalCase::factory()->create();

        // Act
        $response = $this->postJson("/api/your-endpoint/{$case->id}", [
            'param' => 'value',
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'expected_field',
            ],
        ]);
    }
}
```

### Best Practices

1. **Arrange-Act-Assert Pattern**: Always structure tests clearly
2. **One Assertion Per Test**: Focus each test on one behavior
3. **Descriptive Test Names**: Use `it_does_something_when_condition` format
4. **Mock External Dependencies**: Mock APIs, databases (when appropriate)
5. **Use Factories**: Create test data with factories, not manual arrays
6. **Clean Up**: Always use `tearDown()` to close Mockery
7. **Database Transactions**: Use `RefreshDatabase` for clean slate
8. **Test Edge Cases**: Test error conditions, not just happy paths

---

## Continuous Integration

### GitHub Actions Workflow

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo_sqlite

      - name: Install dependencies
        run: composer install --no-interaction

      - name: Run unit tests
        run: php artisan test --testsuite=Unit

      - name: Run feature tests
        run: php artisan test --testsuite=Feature

      - name: Generate coverage
        run: php artisan test --coverage --min=80
```

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running tests before commit..."
php artisan test --stop-on-failure

if [ $? -ne 0 ]; then
    echo "Tests failed. Commit aborted."
    exit 1
fi

echo "Tests passed. Proceeding with commit."
```

---

## Test Data

### Model Factories

Model factories are defined in `database/factories/`:

- `LegalCaseFactory.php`
- `CaseFeatureFactory.php`
- `CasePredictionFactory.php`
- `CaseStrategyFactory.php`
- `CourtDecisionFactory.php`
- `DecisionImpactMetricFactory.php`

### Using Factories

```php
// Create a single case
$case = LegalCase::factory()->create();

// Create with specific attributes
$case = LegalCase::factory()->create([
    'court' => 'Supreme Court',
    'jurisdiction' => 'HR',
]);

// Create multiple
$cases = LegalCase::factory()->count(10)->create();

// With relationships
$case = LegalCase::factory()
    ->has(CaseFeature::factory())
    ->hasDocuments(5)
    ->create();
```

---

## Debugging Tests

### Enable Verbose Output

```bash
php artisan test --testdox
```

### Debug Specific Test

```php
/** @test */
public function it_debugs_something()
{
    dump($variable); // Output variable
    dd($variable);   // Dump and die

    $this->assertTrue(true); // Breakpoint here
}
```

### Database Inspection

```php
// View database state
$this->assertDatabaseHas('cases', ['id' => $case->id]);
$this->assertDatabaseCount('cases', 1);

// Dump database
DB::table('cases')->get()->dump();
```

---

## Test Maintenance

### Monthly Tasks

- [ ] Review and update test coverage
- [ ] Remove obsolete tests
- [ ] Update mocks for API changes
- [ ] Verify integration tests with live APIs

### When Adding Features

- [ ] Write unit tests for new service methods
- [ ] Write API tests for new endpoints
- [ ] Write integration tests for new workflows
- [ ] Update this documentation

---

**Last Updated:** 2025-10-28
**Test Framework:** PHPUnit 10.x
**Total Tests:** 50+ unit, 15+ API, 7+ integration
**Coverage:** 83% overall, 85% services
