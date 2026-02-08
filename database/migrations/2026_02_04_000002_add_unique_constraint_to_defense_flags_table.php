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
            $table->dropIndex(['case_id', 'tactic']);
            $table->unique(['case_id', 'tactic'], 'defense_flags_case_tactic_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('defense_flags')) {
            return;
        }

        Schema::table('defense_flags', function (Blueprint $table) {
            $table->dropUnique('defense_flags_case_tactic_unique');
            $table->index(['case_id', 'tactic']);
        });
    }
};
