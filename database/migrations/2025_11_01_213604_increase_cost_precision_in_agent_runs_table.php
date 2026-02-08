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
        Schema::table('agent_runs', function (Blueprint $table) {
            // Increase precision to 8 decimal places to track small cost increments
            $table->decimal('cost_budget', 10, 8)->nullable()->change();
            $table->decimal('cost_spent', 10, 8)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            // Revert to original precision
            $table->decimal('cost_budget', 10, 4)->nullable()->change();
            $table->decimal('cost_spent', 10, 4)->default(0)->change();
        });
    }
};
