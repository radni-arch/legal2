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
        // Increase cost precision from 4 to 8 decimal places
        // Needed for accurate cost tracking: $0.00002 per 1K tokens requires 5+ decimal places
        Schema::table('embedding_batches', function (Blueprint $table) {
            $table->decimal('cost', 10, 8)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert cost precision back to 4 decimal places
        Schema::table('embedding_batches', function (Blueprint $table) {
            $table->decimal('cost', 10, 4)->default(0)->change();
        });
    }
};
