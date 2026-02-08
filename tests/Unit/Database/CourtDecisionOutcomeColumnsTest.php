<?php

namespace Tests\Unit\Database;

use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourtDecisionOutcomeColumnsTest extends TestCase
{
    use RefreshDatabase;

    private string $tableName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');
    }

    /** @test */
    public function test_migration_adds_outcome_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'outcome'),
            'The court_decisions table should have an outcome column'
        );

        // Verify column type
        $columnType = Schema::getColumnType($this->tableName, 'outcome');
        $this->assertContains($columnType, ['string', 'varchar'], 'outcome column should be a string type');
    }

    /** @test */
    public function test_migration_adds_holding_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'holding'),
            'The court_decisions table should have a holding column'
        );

        // Verify column type
        $columnType = Schema::getColumnType($this->tableName, 'holding');
        $this->assertEquals('text', $columnType, 'holding column should be a text type');
    }

    /** @test */
    public function test_migration_adds_precedential_value_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'precedential_value'),
            'The court_decisions table should have a precedential_value column'
        );

        // Verify column type
        $columnType = Schema::getColumnType($this->tableName, 'precedential_value');
        $this->assertContains($columnType, ['string', 'varchar'], 'precedential_value column should be a string type');
    }

    /** @test */
    public function test_columns_are_nullable(): void
    {
        // Create a court decision with null values for new columns
        $decision = CourtDecision::create([
            'id' => 'test-' . uniqid(),
            'case_number' => 'TEST-001',
            'title' => 'Test Case',
            'outcome' => null,
            'holding' => null,
            'precedential_value' => null,
        ]);

        // Verify the record was created successfully with null values
        $this->assertDatabaseHas($this->tableName, [
            'id' => $decision->id,
            'outcome' => null,
            'holding' => null,
            'precedential_value' => null,
        ]);
    }

    /** @test */
    public function test_indexes_exist_for_queryable_columns(): void
    {
        // Note: This test uses PostgreSQL-specific queries (pg_indexes)
        // Laravel doesn't provide a direct Schema::hasIndex() method,
        // but we can query the database for index information
        $connection = Schema::getConnection();

        // Get indexes from PostgreSQL
        $indexes = $connection->select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = ?
            AND schemaname = 'public'
        ", [$this->tableName]);

        $indexNames = array_map(fn($idx) => $idx->indexname, $indexes);
        $indexDefs = array_map(fn($idx) => $idx->indexdef, $indexes);

        // Check for outcome index
        $hasOutcomeIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, '(outcome)');
        });

        $this->assertTrue(
            $hasOutcomeIndex,
            'outcome column should have an index. Found indexes: ' . implode(', ', $indexNames)
        );

        // Check for precedential_value index
        $hasPrecedentialIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, '(precedential_value)');
        });

        $this->assertTrue(
            $hasPrecedentialIndex,
            'precedential_value column should have an index. Found indexes: ' . implode(', ', $indexNames)
        );
    }
}
