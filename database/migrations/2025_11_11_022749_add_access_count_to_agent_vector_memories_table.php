<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sprint 5.7: Add access_count for tracking memory usage
     */
    public function up(): void
    {
        if (! Schema::hasTable('agent_vector_memories')) {
            return;
        }

        Schema::table('agent_vector_memories', function (Blueprint $table) {
            $table->unsignedInteger('access_count')->default(0)->after('embedding_vector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('agent_vector_memories')) {
            return;
        }

        Schema::table('agent_vector_memories', function (Blueprint $table) {
            $table->dropColumn('access_count');
        });
    }
};
