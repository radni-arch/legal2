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
        // Check if the 'embedding' column exists (pgvector environment)
        // If not, skip this migration as it only applies to pgvector setups
        if (Schema::hasColumn('laws', 'embedding')) {
            Schema::table('laws', function (Blueprint $table) {
                $table->vector('embedding', 1536)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('laws', 'embedding')) {
            Schema::table('laws', function (Blueprint $table) {
                $table->vector('embedding', 1536)->nullable(false)->change();
            });
        }
    }
};
