<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('case_number', 50);
            $table->string('register', 20);
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('year');
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('judge_id')->nullable()->constrained('judges')->nullOnDelete();
            $table->string('judge_name')->nullable();
            $table->string('case_type', 100)->nullable();
            $table->string('decision_type', 100)->nullable();
            $table->string('register_name')->nullable();

            // Key dates
            $table->dateTime('date_filed')->nullable();
            $table->dateTime('date_assigned')->nullable();
            $table->dateTime('date_decision')->nullable();
            $table->dateTime('date_dispatched')->nullable();
            $table->dateTime('date_final')->nullable();
            $table->dateTime('date_enforceable')->nullable();
            $table->dateTime('date_archived')->nullable();
            $table->dateTime('date_appeal')->nullable();
            $table->dateTime('date_retention')->nullable();
            $table->dateTime('date_process_start')->nullable();

            // Calculated fields
            $table->unsignedSmallInteger('processing_days')->nullable();
            $table->boolean('is_same_day')->default(false);
            $table->boolean('is_search_warrant')->default(false);
            $table->boolean('is_weekend')->default(false);

            // Flags
            $table->text('case_at_higher_court')->nullable();
            $table->text('case_outside_court')->nullable();
            $table->text('wrongly_registered')->nullable();
            $table->string('permanent_service', 100)->nullable();

            $table->json('raw_data')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('api_last_update')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['court_id', 'case_number']);
            $table->index('external_id');
            $table->index(['court_id', 'year']);
            $table->index(['court_id', 'judge_id', 'year']);
            $table->index('decision_type');
            $table->index(['is_search_warrant', 'year']);
            $table->index(['judge_id', 'is_search_warrant']);
            $table->index(['year', 'is_search_warrant']);
            $table->index('is_same_day');
            $table->index('is_weekend');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_cases');
    }
};
