# Database Seeders

This directory contains well-organized, reusable database seeders for testing and development.

## Overview

All seeders follow these principles:
- **TDD-tested**: Every seeder has comprehensive unit tests
- **Idempotent**: Can be run multiple times without creating duplicates
- **Environment-aware**: Adjust data volume for testing vs development
- **Well-documented**: Clear usage examples and dependencies

## Quick Start

### Seed All Test Data

```bash
# Fresh database with all test data
php artisan migrate:fresh --seed --seeder=TestDataSeeder

# Or just seed (assumes migrations are done)
php artisan db:seed --class=TestDataSeeder
```

This creates:
- 5 users (1 admin + 4 regular)
- 3 legal cases with 6 documents
- 8 reference keywords

## Available Seeders

### TestDataSeeder (Master Seeder)

Calls all sub-seeders in the correct order. Use this for a complete test database.

```bash
php artisan db:seed --class=TestDataSeeder
```

**Creates:**
- Reference data
- Users
- Legal cases and documents

**Dependencies:** None (calls all other seeders)

---

### UserTestSeeder

Creates test users including an admin account.

```bash
php artisan db:seed --class=UserTestSeeder
```

**Creates:**
- 1 admin user: `admin@example.com` / `password`
- 4 regular users

**Dependencies:** None

---

### LegalCaseTestSeeder

Creates test legal cases with associated documents.

```bash
php artisan db:seed --class=LegalCaseTestSeeder
```

**Creates:**
- 3 legal cases (TEST-CASE-0001, TEST-CASE-0002, TEST-CASE-0003)
- 2 documents per case (6 total)

**Dependencies:** None (cases are self-contained)

---

### ReferenceDataSeeder

Seeds lookup tables and reference data.

```bash
php artisan db:seed --class=ReferenceDataSeeder
```

**Creates:**
- Eoglasna keywords (8 entries)
- Other reference data as needed

**Dependencies:** None

---

### EoglasnaKeywordSeeder

Seeds specific Eoglasna monitoring keywords.

```bash
php artisan db:seed --class=EoglasnaKeywordSeeder
```

**Creates:**
- 8 Eoglasna keywords for monitoring

**Dependencies:** None

## Usage in Tests

All seeders are designed to work seamlessly with Laravel's testing framework.

### Basic Usage

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TestDataSeeder;

class MyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed all test data
        $this->seed(TestDataSeeder::class);
    }

    public function test_something(): void
    {
        // Database now has users, cases, etc.
        $this->assertEquals(5, User::count());
    }
}
```

### Seed Specific Data

```php
// Only seed users
$this->seed(UserTestSeeder::class);

// Only seed legal cases (assumes users exist if needed)
$this->seed(LegalCaseTestSeeder::class);
```

### Multiple Seeders

```php
protected function setUp(): void
{
    parent::setUp();

    // Seed only what you need
    $this->seed([
        UserTestSeeder::class,
        ReferenceDataSeeder::class,
    ]);
}
```

## Running Tests

All seeders have comprehensive unit tests.

```bash
# Run all seeder tests
php artisan test tests/Unit/Seeders/

# Run specific seeder test
php artisan test --filter UserTestSeederTest
php artisan test --filter LegalCaseTestSeederTest
php artisan test --filter TestDataSeederTest
```

## Idempotency

All seeders are idempotent - they can be run multiple times safely:

```bash
# These commands won't create duplicates
php artisan db:seed --class=TestDataSeeder
php artisan db:seed --class=TestDataSeeder
php artisan db:seed --class=TestDataSeeder
```

How it works:
- **UserTestSeeder**: Uses `firstOrCreate` with email as unique key
- **LegalCaseTestSeeder**: Uses `firstOrCreate` with case_number as unique key
- **ReferenceDataSeeder**: Uses `firstOrCreate` for keywords

## Factory Reference

Each seeder uses Laravel factories for data generation. Available factories:

### User & Auth (1)
- `UserFactory`

### Legal Cases (6)
- `LegalCaseFactory`
- `CaseDocumentFactory`
- `CaseFeatureFactory`
- `CasePredictionFactory`
- `CaseStrategyFactory`
- `EvidenceFactory`

### Court System (6)
- `CourtDecisionFactory`
- `CourtDecisionDocumentFactory`
- `LawFactory`
- `IngestedLawFactory`
- `LawUploadFactory`
- `DecisionImpactMetricFactory`

### AI/Agent (7)
- `AgentCollaborationFactory`
- `AgentCommunicationFactory`
- `AgentExecutionFactory`
- `AgentRunFactory`
- `AgentVectorMemoryFactory`
- `AiReasoningTraceFactory`
- `LearningOpportunityFactory`

### Document Processing (5)
- `DocumentContextFactory`
- `DocumentGenerationRunFactory`
- `DocumentIterationFactory`
- `TextractDocumentFactory`
- `TextractJobFactory`

### Embeddings/Citations (3)
- `EmbeddingBatchFactory`
- `LegalFactPatternFactory`
- `CitationProvenanceFactory`

### Ekom System (3)
- `EkomOtpravakFactory`
- `EkomPodnesakFactory`
- `EkomPredmetFactory`

### Eoglasna System (4)
- `EoglasnaKeywordFactory`
- `EoglasnaKeywordMatchFactory`
- `EoglasnaNoticeFactory`
- `EoglasnaOsijekMonitoringFactory`

## Extending Seeders

To add a new seeder:

### 1. Write Test First (TDD)

```php
// tests/Unit/Seeders/MyNewSeederTest.php
class MyNewSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_data(): void
    {
        $this->seed(MyNewSeeder::class);
        $this->assertDatabaseCount('my_table', 10);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MyNewSeeder::class);
        $firstCount = MyModel::count();

        $this->seed(MyNewSeeder::class);
        $secondCount = MyModel::count();

        $this->assertEquals($firstCount, $secondCount);
    }
}
```

### 2. Run Test (Should Fail)

```bash
php artisan test --filter MyNewSeederTest
# Expected: FAIL - Seeder doesn't exist
```

### 3. Implement Seeder

```php
// database/seeders/MyNewSeeder.php
class MyNewSeeder extends Seeder
{
    public function run(): void
    {
        // Use firstOrCreate for idempotency
        for ($i = 1; $i <= 10; $i++) {
            MyModel::firstOrCreate(
                ['unique_field' => "VALUE-{$i}"],
                [
                    'other_field' => "Data {$i}",
                ]
            );
        }

        if ($this->command) {
            $this->command->info('✓ Created data');
        }
    }
}
```

### 4. Run Test (Should Pass)

```bash
php artisan test --filter MyNewSeederTest
# Expected: PASS
```

### 5. Add to TestDataSeeder

```php
$this->call([
    ReferenceDataSeeder::class,
    UserTestSeeder::class,
    LegalCaseTestSeeder::class,
    MyNewSeeder::class,  // Add here
]);
```

## Best Practices

1. **Always use factories**: Don't hardcode model creation
2. **Test everything**: Every seeder should have tests
3. **Make it idempotent**: Use `firstOrCreate` or similar patterns
4. **Respect dependencies**: Seed in correct order
5. **Keep it small**: Test seeders should create minimal realistic data
6. **Document well**: Add usage examples to seeder docblocks

## Troubleshooting

### Seeder creates duplicates

Ensure you're using `firstOrCreate` with a unique field:

```php
// Bad - creates duplicates
MyModel::factory()->create(['name' => 'Test']);

// Good - idempotent
MyModel::firstOrCreate(
    ['name' => 'Test'],
    ['other' => 'data']
);
```

### Foreign key violations

Ensure seeders are called in dependency order:

```php
$this->call([
    ReferenceDataSeeder::class,  // No dependencies
    UserTestSeeder::class,        // No dependencies
    LegalCaseTestSeeder::class,   // May reference users
]);
```

### Tests fail in CI/CD

Ensure `RefreshDatabase` trait is used:

```php
class MyTest extends TestCase
{
    use RefreshDatabase;  // Important!

    public function test_something(): void
    {
        $this->seed(TestDataSeeder::class);
        // ...
    }
}
```

## Performance

Seeding performance benchmarks:

| Seeder | Records | Time |
|--------|---------|------|
| UserTestSeeder | 5 users | ~0.6s |
| LegalCaseTestSeeder | 3 cases + 6 docs | ~0.1s |
| ReferenceDataSeeder | 8 keywords | ~0.05s |
| **TestDataSeeder (all)** | **All data** | **~0.8s** |

## Contributing

When adding new seeders:

1. Follow TDD: Test first, then implement
2. Make it idempotent
3. Add comprehensive tests
4. Update this README
5. Add to TestDataSeeder if appropriate

## Related Documentation

- [Laravel Database Seeding](https://laravel.com/docs/seeding)
- [Laravel Factories](https://laravel.com/docs/database-testing#factories)
- [Testing Documentation](../../tests/README.md)
