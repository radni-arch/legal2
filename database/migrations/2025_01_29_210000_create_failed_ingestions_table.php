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
        Schema::create('failed_ingestions', function (Blueprint $table) {
            $table->id();

            // Decision identifier
            $table->string('decision_id', 100)->index();
            $table->string('source_type', 50)->default('odluke'); // odluke, manual, etc.

            // Failure tracking
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->string('status', 50)->default('pending'); // pending, retrying, failed, succeeded

            // Error details
            $table->string('failure_reason', 255)->nullable();
            $table->json('error_details')->nullable(); // Full error context
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_attempted_at')->nullable();

            // Retry configuration
            $table->integer('retry_delay_seconds')->default(60); // Exponential backoff
            $table->timestamp('next_retry_at')->nullable()->index();

            // Success tracking
            $table->timestamp('succeeded_at')->nullable();
            $table->json('success_details')->nullable();

            // Ingestion options (preserved for retry)
            $table->json('ingestion_options')->nullable();

            // Metadata for debugging
            $table->json('decision_meta')->nullable(); // Court, date, etc.
            $table->string('queued_by', 100)->nullable(); // User/system that triggered

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient querying
            $table->index(['status', 'next_retry_at']);
            $table->index('created_at');
            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_ingestions');
    }
};
