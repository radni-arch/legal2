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
        Schema::create('court_case_decision_matches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('court_case_id');
            $table->string('court_decision_id', 26);
            $table->timestamp('matched_at');
            $table->string('match_type', 20); // 'auto', 'manual'
            $table->unsignedTinyInteger('match_confidence'); // 0-100
            $table->string('match_source', 50); // 'epredmet_fetch', 'decision_ingest', 'manual', 'scheduled_job'
            $table->string('verification_status', 20)->default('pending'); // 'pending', 'verified', 'rejected'
            $table->json('match_criteria');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['court_case_id', 'court_decision_id'], 'unique_case_decision_match');
            $table->index('court_case_id');
            $table->index('court_decision_id');
            $table->index('verification_status');
            $table->index('matched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_case_decision_matches');
    }
};
