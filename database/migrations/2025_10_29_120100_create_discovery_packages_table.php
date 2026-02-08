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
        Schema::create('discovery_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('fact_pattern_id')->constrained('legal_fact_patterns')->onDelete('cascade');

            // Discovery requests (JSON structure)
            $table->json('requests');
            // Structure:
            // {
            //   "interrogatories": {"total": 25, "items": [...]},
            //   "document_requests": {"total": 15, "items": [...]},
            //   "requests_for_admission": {"total": 10, "items": [...]},
            //   "deposition_notices": {"total": 3, "notices": [...]}
            // }

            // Metadata (JSON)
            $table->json('metadata')->nullable();
            // Structure:
            // {
            //   "estimated_cost": {"total_min": 8000, "total_max": 18000, "breakdown": {...}},
            //   "timeline": {"phase_1": {...}, "phase_2": {...}, "phase_3": {...}},
            //   "generated_at": "...",
            //   "complexity_score": 0.75
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
        Schema::dropIfExists('discovery_packages');
    }
};
