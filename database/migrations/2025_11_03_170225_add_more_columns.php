<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('agent_vector_memories')) {
            return;
        }

        if (! Schema::hasColumn('agent_vector_memories', 'embedding_vector')) {
            Schema::table('agent_vector_memories', function (Blueprint $table) {
                $table->vector('embedding_vector', 1536)->nullable();
            });
        }

        if (Schema::hasColumn('agent_vector_memories', 'embedding')) {
            Schema::table('agent_vector_memories', function (Blueprint $table) {
                $table->vector('embedding', 1536)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
