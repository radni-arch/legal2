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
        Schema::create('legal_precedents', function (Blueprint $table) {
            $table->id();

            // Case identification
            $table->string('case_number');
            $table->string('court', 20);  // USRH, VSRH, ECHR, etc.
            $table->string('court_full')->nullable();
            $table->date('decision_date');

            // Parties
            $table->string('applicant')->nullable();
            $table->string('respondent')->nullable();
            $table->string('echr_app_number')->nullable();  // e.g., "68955/11"

            // Legal content
            $table->text('legal_issue')->nullable();
            $table->text('key_holding')->nullable();
            $table->text('key_quote')->nullable();
            $table->string('quote_language', 5)->nullable();  // hr, en, etc.
            $table->text('relevance_to_case')->nullable();

            // Structured metadata (JSONB columns)
            $table->jsonb('articles_interpreted')->nullable();  // ["ECHR Art.6", "Ustav RH čl.29"]
            $table->jsonb('argument_types')->nullable();  // ["equality_of_arms", "file_access"]
            $table->jsonb('tags')->nullable();  // ["ustavni_sud", "right_to_appeal"]

            // Source reference
            $table->string('source_url')->nullable();
            $table->string('nn_reference')->nullable();  // Narodne Novine reference

            $table->timestamps();

            // Indexes for common queries
            $table->index('court');
            $table->index('decision_date');
            $table->unique(['case_number', 'court']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_precedents');
    }
};
