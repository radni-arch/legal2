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
        Schema::create('home_search_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 100)->unique();
            $table->string('court', 200)->nullable();
            $table->string('judge', 200)->nullable();
            $table->date('decision_date')->nullable();
            $table->string('offense_type', 50)->nullable();
            $table->text('offense_description')->nullable();
            $table->string('offense_severity', 50)->nullable();
            $table->string('search_type', 100)->nullable();
            $table->boolean('evidence_found')->nullable();
            $table->boolean('evidence_suppressed')->nullable();
            $table->json('legal_violations')->nullable();
            $table->json('zkp_articles_cited')->nullable();
            $table->boolean('proportionality_mentioned')->nullable();
            $table->boolean('constitutional_rights_mentioned')->nullable();
            $table->text('source_url')->nullable();
            $table->decimal('extraction_confidence', 3, 2)->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('court');
            $table->index('decision_date');
            $table->index('offense_type');
            $table->index(['court', 'decision_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_search_cases');
    }
};
