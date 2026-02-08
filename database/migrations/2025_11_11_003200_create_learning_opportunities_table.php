<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sprint 5.1: Learning Opportunity Detection
     *
     * Creates table for storing low-confidence AI outputs
     * that need human review for active learning.
     */
    public function up(): void
    {
        if (Schema::hasTable('learning_opportunities')) {
            return;
        }

        Schema::create('learning_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_type', 50);
            $table->string('source_type', 50);
            $table->bigInteger('source_id');
            $table->json('ai_output');
            $table->decimal('confidence_score', 3, 2);
            $table->text('uncertainty_reason')->nullable();
            $table->string('status', 50)->default('pending');
            $table->json('human_label')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('incorporated_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('opportunity_type');
            $table->index('confidence_score');
            $table->index(['source_type', 'source_id']); // Prevent duplicates
            $table->index('created_at');

            // Foreign key
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_opportunities');
    }
};
