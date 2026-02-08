<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Topic Spike Events Table (Sprint 8.2)
 *
 * Stores detected topic spikes for alerting and historical analysis.
 * Populated by AnalyzeTopicTrendsCommand running weekly.
 *
 * Schema from Sprint 8.2 specification:
 * - topic_name: Name of the topic that spiked
 * - baseline_count: 4-week average before spike
 * - current_count: Current week count
 * - percent_increase: Percentage increase from baseline
 * - detected_at: When the spike was detected
 * - affected_courts: JSON array of courts showing the spike
 * - severity: Classification (minor/moderate/major)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_spike_events', function (Blueprint $table) {
            $table->id();
            $table->string('topic_name', 255)->index();
            $table->integer('baseline_count');
            $table->integer('current_count');
            $table->decimal('percent_increase', 5, 2); // Up to 999.99%
            $table->timestamp('detected_at')->index();
            $table->json('affected_courts')->nullable(); // List of courts
            $table->string('severity', 20)->index(); // minor, moderate, major
            $table->timestamps();

            // Index for querying recent spikes
            $table->index(['topic_name', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_spike_events');
    }
};
