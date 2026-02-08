<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_impact_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('decision_id')->constrained('court_decisions')->onDelete('cascade');

            // Citation metrics
            $table->unsignedInteger('citation_count')->default(0);
            $table->unsignedInteger('direct_citations')->default(0); // directly cited
            $table->unsignedInteger('indirect_citations')->default(0); // cited through others

            // Authority scoring
            $table->float('authority_score')->default(0); // PageRank-style score 0-1
            $table->float('precedent_strength')->default(0); // how strong as precedent 0-1
            $table->float('influence_score')->default(0); // overall influence 0-1

            // Temporal metrics
            $table->json('citations_over_time')->nullable(); // time series data
            $table->float('citation_velocity')->default(0); // citations per month
            $table->float('temporal_decay_factor')->default(1.0); // age adjustment

            // Jurisdictional spread
            $table->json('jurisdictional_spread')->nullable(); // which jurisdictions cite it
            $table->unsignedInteger('jurisdictions_count')->default(0);

            // Court hierarchy metrics
            $table->json('citing_courts')->nullable(); // which courts cite it
            $table->unsignedInteger('higher_court_citations')->default(0);
            $table->unsignedInteger('same_court_citations')->default(0);
            $table->unsignedInteger('lower_court_citations')->default(0);

            // Impact analysis
            $table->json('influential_cases')->nullable(); // key cases that followed
            $table->text('impact_summary')->nullable(); // LLM-generated summary

            // Metadata
            $table->timestamp('last_calculated_at');
            $table->timestamp('last_citation_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('authority_score');
            $table->index('precedent_strength');
            $table->index('citation_count');
            $table->index('last_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_impact_metrics');
    }
};
