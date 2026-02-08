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
        if (Schema::hasTable('benchmark_runs')) {
            return;
        }

        Schema::create('benchmark_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Benchmark identification
            $table->string('benchmark_class');
            $table->string('benchmark_name');
            $table->text('description')->nullable();

            // Git tracking for comparison
            $table->string('git_commit_hash', 40);
            $table->string('git_branch')->nullable();
            $table->boolean('git_dirty')->default(false); // Has uncommitted changes

            // Execution metadata
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable(); // Milliseconds
            $table->string('status')->default('running'); // running, completed, failed

            // Configuration
            $table->json('config')->nullable(); // Benchmark configuration options

            // Results
            $table->json('metrics'); // Scores, accuracy, latency, etc.
            $table->json('details')->nullable(); // Detailed results, samples, errors

            // Environment info
            $table->string('php_version')->nullable();
            $table->string('laravel_version')->nullable();
            $table->json('system_info')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('benchmark_class');
            $table->index('benchmark_name');
            $table->index('git_commit_hash');
            $table->index('status');
            $table->index('started_at');
            $table->index(['benchmark_class', 'git_commit_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benchmark_runs');
    }
};
