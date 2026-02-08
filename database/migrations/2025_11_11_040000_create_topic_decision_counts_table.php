<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Topic Decision Counts Table (Sprint 8.2)
 *
 * Stores aggregated counts of decisions per topic per week for trend analysis.
 * Used by TopicAnalyticsService to calculate baselines and detect spikes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_decision_counts', function (Blueprint $table) {
            $table->id();
            $table->string('topic_name', 255)->index();
            $table->integer('decision_count')->default(0);
            $table->date('week_start')->index(); // Start of the week (Monday)
            $table->timestamps();

            // Composite index for efficient queries
            $table->index(['topic_name', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_decision_counts');
    }
};
