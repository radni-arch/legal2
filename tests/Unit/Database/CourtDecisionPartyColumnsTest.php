<?php

namespace Tests\Unit\Database;

use App\Models\CourtDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourtDecisionPartyColumnsTest extends TestCase
{
    use RefreshDatabase;

    private string $tableName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tableName = config('vizra-adk.tables.court_decisions', 'court_decisions');
    }

    /** @test */
    public function test_migration_adds_plaintiff_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'plaintiff'),
            'The court_decisions table should have a plaintiff column'
        );

        // Verify column type
        $columnType = Schema::getColumnType($this->tableName, 'plaintiff');
        $this->assertContains($columnType, ['string', 'varchar'], 'plaintiff column should be a string type');
    }

    /** @test */
    public function test_migration_adds_defendant_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'defendant'),
            'The court_decisions table should have a defendant column'
        );

        // Verify column type
        $columnType = Schema::getColumnType($this->tableName, 'defendant');
        $this->assertContains($columnType, ['string', 'varchar'], 'defendant column should be a string type');
    }

    /** @test */
    public function test_party_columns_are_nullable(): void
    {
        // Create a court decision with null values for party columns
        $decision = CourtDecision::create([
            'id' => 'test-' . uniqid(),
            'case_number' => 'TEST-001',
            'title' => 'Test Case',
            'plaintiff' => null,
            'defendant' => null,
        ]);

        // Verify the record was created successfully with null values
        $this->assertDatabaseHas($this->tableName, [
            'id' => $decision->id,
            'plaintiff' => null,
            'defendant' => null,
        ]);
    }

    /** @test */
    public function test_party_columns_accept_string_values(): void
    {
        // Create a court decision with party names
        $decision = CourtDecision::create([
            'id' => 'test-' . uniqid(),
            'case_number' => 'TEST-002',
            'title' => 'Test Case with Parties',
            'plaintiff' => 'John Doe',
            'defendant' => 'Jane Smith',
        ]);

        // Verify the record was created successfully with party values
        $this->assertDatabaseHas($this->tableName, [
            'id' => $decision->id,
            'plaintiff' => 'John Doe',
            'defendant' => 'Jane Smith',
        ]);
    }

    /** @test */
    public function test_indexes_exist_for_party_columns(): void
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

        // Check for plaintiff index
        $hasPlaintiffIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, '(plaintiff)');
        });

        $this->assertTrue(
            $hasPlaintiffIndex,
            'plaintiff column should have an index. Found indexes: ' . implode(', ', $indexNames)
        );

        // Check for defendant index
        $hasDefendantIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, '(defendant)');
        });

        $this->assertTrue(
            $hasDefendantIndex,
            'defendant column should have an index. Found indexes: ' . implode(', ', $indexNames)
        );
    }
}
