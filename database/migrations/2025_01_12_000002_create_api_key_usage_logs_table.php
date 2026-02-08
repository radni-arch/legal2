<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');

            // Request details
            $table->string('model_used')->nullable();
            $table->string('endpoint')->nullable()->comment('chat/completions, generateContent, etc');
            $table->string('task_type')->default('general')->comment('pdf|vision|text|general');

            // Token usage
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Response info
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('was_successful')->default(true);
            $table->boolean('was_rate_limited')->default(false);
            $table->boolean('was_fallback')->default(false)->comment('Used as fallback from another key');

            // Rate limit headers captured (for debugging/analysis)
            $table->json('rate_limit_headers')->nullable();

            // Error info
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Context
            $table->string('document_id')->nullable()->index()->comment('For tracing specific document processing');
            $table->string('batch_id')->nullable()->index()->comment('For batch processing correlation');

            $table->timestamp('created_at')->useCurrent();

            // Indexes for analytics
            $table->index(['api_key_id', 'created_at']);
            $table->index(['was_rate_limited', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_usage_logs');
    }
};
