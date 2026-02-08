<?php

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResearchSessionsTableTest extends TestCase
{
    use RefreshDatabase;

    private string $tableName = 'research_sessions';

    /** @test */
    public function test_research_sessions_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable($this->tableName),
            'The research_sessions table should exist'
        );
    }

    /** @test */
    public function test_migration_adds_required_columns(): void
    {
        $requiredColumns = [
            'id',
            'uuid',
            'user_id',
            'name',
            'description',
            'viewed_nodes',
            'pinned_nodes',
            'expanded_nodes',
            'alerts',
            'root_node_id',
            'filter_settings',
            'last_activity_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn($this->tableName, $column),
                "The research_sessions table should have a {$column} column"
            );
        }
    }

    /** @test */
    public function test_uuid_column_type(): void
    {
        $columnType = Schema::getColumnType($this->tableName, 'uuid');
        $this->assertContains(
            $columnType,
            ['string', 'varchar', 'uuid'],
            'uuid column should be a string/uuid type'
        );
    }

    /** @test */
    public function test_json_columns_have_correct_type(): void
    {
        $jsonColumns = [
            'viewed_nodes',
            'pinned_nodes',
            'expanded_nodes',
            'alerts',
            'filter_settings',
        ];

        foreach ($jsonColumns as $column) {
            $columnType = Schema::getColumnType($this->tableName, $column);
            $this->assertContains(
                $columnType,
                ['json', 'jsonb', 'text'],
                "{$column} should be a JSON type"
            );
        }
    }

    /** @test */
    public function test_indexes_exist_for_performance(): void
    {
        $connection = Schema::getConnection();

        // Get indexes from PostgreSQL
        $indexes = $connection->select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = ?
            AND schemaname = 'public'
        ", [$this->tableName]);

        $indexDefs = array_map(fn($idx) => $idx->indexdef, $indexes);
        $indexNames = array_map(fn($idx) => $idx->indexname, $indexes);

        // Check for user_id + last_activity_at composite index
        $hasUserActivityIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, 'user_id') && str_contains($def, 'last_activity_at');
        });

        $this->assertTrue(
            $hasUserActivityIndex,
            'Should have composite index on (user_id, last_activity_at). Found indexes: ' . implode(', ', $indexNames)
        );

        // Check for user_id + created_at composite index
        $hasUserCreatedIndex = collect($indexDefs)->contains(function ($def) {
            return str_contains($def, 'user_id') && str_contains($def, 'created_at');
        });

        $this->assertTrue(
            $hasUserCreatedIndex,
            'Should have composite index on (user_id, created_at). Found indexes: ' . implode(', ', $indexNames)
        );
    }

    /** @test */
    public function test_uuid_column_has_unique_constraint(): void
    {
        $connection = Schema::getConnection();

        // Get unique constraints from PostgreSQL
        $constraints = $connection->select("
            SELECT conname, pg_get_constraintdef(oid) as definition
            FROM pg_constraint
            WHERE conrelid = (
                SELECT oid FROM pg_class WHERE relname = ? AND relnamespace = (
                    SELECT oid FROM pg_namespace WHERE nspname = 'public'
                )
            )
            AND contype = 'u'
        ", [$this->tableName]);

        $constraintDefs = array_map(fn($c) => strtolower($c->definition), $constraints);

        $hasUuidUnique = collect($constraintDefs)->contains(function ($def) {
            return str_contains($def, 'uuid');
        });

        $this->assertTrue(
            $hasUuidUnique,
            'uuid column should have a unique constraint'
        );
    }

    /** @test */
    public function test_foreign_key_constraint_on_user_id(): void
    {
        $connection = Schema::getConnection();

        // Get foreign key constraints from PostgreSQL
        $foreignKeys = $connection->select("
            SELECT
                conname,
                pg_get_constraintdef(oid) as definition
            FROM pg_constraint
            WHERE conrelid = (
                SELECT oid FROM pg_class WHERE relname = ? AND relnamespace = (
                    SELECT oid FROM pg_namespace WHERE nspname = 'public'
                )
            )
            AND contype = 'f'
        ", [$this->tableName]);

        $fkDefs = array_map(fn($fk) => strtolower($fk->definition), $foreignKeys);

        $hasUserForeignKey = collect($fkDefs)->contains(function ($def) {
            return str_contains($def, 'user_id') && str_contains($def, 'users');
        });

        $this->assertTrue(
            $hasUserForeignKey,
            'user_id should have a foreign key constraint to users table'
        );
    }

    /** @test */
    public function test_soft_deletes_enabled(): void
    {
        $this->assertTrue(
            Schema::hasColumn($this->tableName, 'deleted_at'),
            'The research_sessions table should have soft deletes (deleted_at column)'
        );

        $columnType = Schema::getColumnType($this->tableName, 'deleted_at');
        $this->assertContains(
            $columnType,
            ['datetime', 'timestamp'],
            'deleted_at should be a datetime/timestamp type'
        );
    }
}
