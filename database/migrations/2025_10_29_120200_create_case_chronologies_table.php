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
        Schema::create('case_chronologies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('fact_pattern_id')->constrained('legal_fact_patterns')->onDelete('cascade');

            // Events in chronological order (JSON array)
            $table->json('events');
            // Structure:
            // [
            //   {
            //     "date": "2024-01-15",
            //     "description": "Contract signed",
            //     "sequence": 1,
            //     "category": "event",
            //     "significance": "Formation of agreement",
            //     "source": "events"
            //   },
            //   ...
            // ]

            // Analysis (JSON structure)
            $table->json('analysis');
            // Structure:
            // {
            //   "time_gaps": [{"after_event": "...", "before_event": "...", "days": 45, "questions": [...]}],
            //   "critical_dates": [{"date": "...", "description": "...", "reason": "..."}],
            //   "timeline_summary": "Narrative summary of events...",
            //   "total_duration": {"days": 180, "description": "6 months"}
            // }

            // Visualization data (JSON structure for charts)
            $table->json('visualization_data')->nullable();
            // Structure:
            // {
            //   "timeline_data": [{"x": "2024-01-15", "y": 1, "label": "..."}],
            //   "gap_visualization": [{"start": "...", "end": "...", "duration": 45}],
            //   "category_breakdown": {"event": 10, "procedural": 5, "evidence": 3}
            // }

            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('fact_pattern_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_chronologies');
    }
};
