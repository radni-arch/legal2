<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('echr_cases', function (Blueprint $table) {
            $table->id();

            // HUDOC identifiers
            $table->string('item_id', 50)->unique()->comment('HUDOC itemid');
            $table->string('application_number', 50)->nullable()->index();
            $table->string('ecli', 100)->nullable()->unique();

            // Case metadata
            $table->string('case_name', 500);
            $table->string('case_name_short', 200)->nullable();
            $table->string('respondent_state', 100)->index();
            $table->string('originating_body', 100)->nullable();
            $table->enum('document_type', [
                'JUDGMENT', 'DECISION', 'COMMUNICATED',
                'ADVISORY_OPINION', 'LEGAL_SUMMARY', 'RESOLUTION',
            ])->default('JUDGMENT');
            $table->enum('importance', ['1', '2', '3', '4'])->nullable()
                  ->comment('1=Key case, 2=High, 3=Medium, 4=Low');

            // Dates
            $table->date('judgment_date')->nullable()->index();
            $table->date('decision_date')->nullable();
            $table->date('introduction_date')->nullable();
            $table->date('publication_date')->nullable();

            // Conclusions
            $table->json('violations')->nullable();
            $table->json('non_violations')->nullable();
            $table->json('conclusion_summary')->nullable();

            // Content
            $table->longText('full_text')->nullable();
            $table->longText('facts')->nullable();
            $table->longText('law_section')->nullable();
            $table->text('legal_summary')->nullable();
            $table->json('keywords')->nullable();
            $table->json('kp_thesaurus')->nullable();

            // External references
            $table->json('external_sources')->nullable();
            $table->json('cited_cases')->nullable();
            $table->string('representedby', 1000)->nullable();
            $table->boolean('has_separate_opinion')->default(false);

            // Languages
            $table->string('language', 10)->default('ENG');
            $table->json('available_languages')->nullable();

            // Processing metadata
            $table->boolean('full_text_downloaded')->default(false);
            $table->boolean('is_analyzed')->default(false);
            $table->json('analysis_results')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['respondent_state', 'judgment_date']);
            $table->index(['importance', 'judgment_date']);
            $table->fullText(['case_name', 'case_name_short']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echr_cases');
    }
};
