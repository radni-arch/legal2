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
        Schema::table('agent_runs', function (Blueprint $table) {
            // Add default value to context column (empty JSON object)
            $table->json('context')->default('{}')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            // Remove default value from context column
            DB::statement('ALTER TABLE agent_runs ALTER COLUMN context DROP DEFAULT');
        });
    }
};
