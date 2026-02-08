<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make embedding columns nullable to support tests and optional embeddings
        $tables = [
            'agent_vector_memories',
            'court_decision_documents',
            'cases_documents',
            'laws',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                // Check if we're using pgvector (vector type) or JSON
                $columns = DB::select("SELECT column_name, data_type, is_nullable
                    FROM information_schema.columns
                    WHERE table_name = ? AND column_name IN ('embedding', 'embedding_vector')",
                    [$table]
                );

                foreach ($columns as $column) {
                    if ($column->is_nullable === 'NO') {
                        if ($column->data_type === 'USER-DEFINED') {
                            // pgvector type - use raw SQL
                            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column->column_name} DROP NOT NULL");
                        } elseif ($column->data_type === 'json' || $column->data_type === 'jsonb') {
                            // JSON column - use Laravel schema
                            Schema::table($table, function (Blueprint $table) use ($column) {
                                $table->json($column->column_name)->nullable()->change();
                            });
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Make embedding columns NOT NULL again
        $tables = [
            'agent_vector_memories',
            'court_decision_documents',
            'cases_documents',
            'laws',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $columns = DB::select("SELECT column_name, data_type
                    FROM information_schema.columns
                    WHERE table_name = ? AND column_name IN ('embedding', 'embedding_vector')",
                    [$table]
                );

                foreach ($columns as $column) {
                    if ($column->data_type === 'USER-DEFINED') {
                        // pgvector type
                        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column->column_name} SET NOT NULL");
                    } elseif ($column->data_type === 'json' || $column->data_type === 'jsonb') {
                        // JSON column
                        Schema::table($table, function (Blueprint $table) use ($column) {
                            $table->json($column->column_name)->nullable(false)->change();
                        });
                    }
                }
            }
        }
    }
};
