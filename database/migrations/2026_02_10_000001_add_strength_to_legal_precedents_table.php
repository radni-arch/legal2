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
        Schema::table('legal_precedents', function (Blueprint $table) {
            $table->string('published_in')->nullable()->after('decision_date');
            $table->string('strength', 20)->default('moderate')->after('relevance_to_case');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_precedents', function (Blueprint $table) {
            $table->dropColumn(['published_in', 'strength']);
        });
    }
};
