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
        Schema::create('legal_memos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('fact_pattern_id')->constrained('legal_fact_patterns')->onDelete('cascade');

            // Memo sections (JSON structure)
            $table->json('sections');
            // Structure:
            // {
            //   "header": {"to": "...", "from": "...", "date": "...", "subject": "..."},
            //   "issue": "...",
            //   "brief_answer": "...",
            //   "facts": "...",
            //   "analysis": [{"issue": "...", "rule": "...", "application": "...", "conclusion": "..."}],
            //   "conclusion": "...",
            //   "recommendations": ["..."]
            // }

            // Metadata (JSON)
            $table->json('metadata')->nullable();
            // Structure:
            // {
            //   "precedents_used": [...],
            //   "generation_options": {...},
            //   "generated_at": "...",
            //   "word_count": 1234,
            //   "estimated_reading_time": 5
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
        Schema::dropIfExists('legal_memos');
    }
};
