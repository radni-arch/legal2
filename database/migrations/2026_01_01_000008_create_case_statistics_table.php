<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->nullable()->constrained('courts')->cascadeOnDelete();
            $table->foreignId('judge_id')->nullable()->constrained('judges')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('register', 20)->nullable();
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('search_warrants')->default(0);
            $table->unsignedInteger('same_day_decisions')->default(0);
            $table->unsignedInteger('weekend_decisions')->default(0);
            $table->unsignedInteger('rejected_requests')->default(0);
            $table->decimal('avg_processing_days', 8, 2)->nullable();
            $table->decimal('concentration_percent', 5, 2)->nullable();
            $table->unsignedInteger('requests_by_soko')->default(0);
            $table->unsignedInteger('requests_by_local_pp')->default(0);
            $table->unsignedInteger('requests_by_dipu')->default(0);
            $table->unsignedInteger('requests_by_other')->default(0);
            $table->timestamps();
            
            $table->unique(['court_id', 'judge_id', 'year', 'month', 'register'], 'stats_unique');
            $table->index(['year', 'court_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_statistics');
    }
};
