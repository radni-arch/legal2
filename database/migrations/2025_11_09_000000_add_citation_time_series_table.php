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
        Schema::create('citation_time_series', function (Blueprint $table) {
            $table->id();
            $table->string('decision_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->integer('citation_count')->default(0);
            $table->integer('incoming_citations')->default(0);
            $table->integer('outgoing_citations')->default(0);
            $table->decimal('avg_citation_importance', 5, 3)->default(0);
            $table->json('citing_courts')->nullable();
            $table->json('top_citing_decisions')->nullable();
            $table->timestamps();

            $table->unique(['decision_id', 'period_start', 'period_type'], 'citation_time_series_unique');

            $table->foreign('decision_id')
                ->references('id')
                ->on('court_decisions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citation_time_series');
    }
};
