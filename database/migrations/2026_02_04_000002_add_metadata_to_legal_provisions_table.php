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
        Schema::table('legal_provisions', function (Blueprint $table) {
            $table->jsonb('rebuts')->default('[]');
            $table->jsonb('complements')->default('[]');
            $table->string('strength')->default('strong');
        });

        DB::table('legal_provisions')
            ->whereNull('rebuts')
            ->update(['rebuts' => json_encode([])]);

        DB::table('legal_provisions')
            ->whereNull('complements')
            ->update(['complements' => json_encode([])]);

        DB::table('legal_provisions')
            ->whereNull('strength')
            ->update(['strength' => 'strong']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_provisions', function (Blueprint $table) {
            $table->dropColumn(['rebuts', 'complements', 'strength']);
        });
    }
};
