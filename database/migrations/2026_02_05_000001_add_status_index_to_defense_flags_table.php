<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('defense_flags')) {
            return;
        }

        Schema::table('defense_flags', function (Blueprint $table) {
            // Add composite index for filtering active flags by case
            $table->index(['case_id', 'status'], 'defense_flags_case_status_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('defense_flags')) {
            return;
        }

        Schema::table('defense_flags', function (Blueprint $table) {
            $table->dropIndex('defense_flags_case_status_idx');
        });
    }
};
